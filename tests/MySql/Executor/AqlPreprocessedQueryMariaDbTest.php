<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\QueryOption;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\AdditionalOptions;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Entities\AqlRoundTripNote;
use IfCastle\AQL\Executor\Exceptions\PropertyNotFound;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\OptionsWithAdditionalHandler;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQuery;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQueryInterface;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQueryStorageInterface;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/**
 * A preprocessed query is normalised once and run many times; each run belongs to the transaction of its own call.
 */
class AqlPreprocessedQueryMariaDbTest extends TestCaseWithDiContainer
{
    private const string INSERT     = 'INSERT INTO AqlRoundTrip SET value = "preprocessed"';

    private \PDO $pdo;

    private SqlRecordingMySql $main;

    private SqlRecordingMySql $remote;

    private PreprocessedQueryStorageInterface $storage;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->dropTables();
        $this->pdo->exec('CREATE TABLE aql_round_trip (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL)'
            . ' ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_round_trip_note (id INT AUTO_INCREMENT PRIMARY KEY, roundTripId INT NOT NULL,'
            . ' label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->main = $this->newStorage(StorageCollectionInterface::STORAGE_MAIN);
        $this->remote = $this->newStorage(AqlRoundTripNote::STORAGE);
        $this->storage = new class implements PreprocessedQueryStorageInterface {
            /** @var array<string, PreprocessedQueryInterface> */
            public array $queries = [];

            #[\Override]
            public function findPreprocessedQuery(string $uniqueKey): ?PreprocessedQueryInterface
            {
                return $this->queries[$uniqueKey] ?? null;
            }

            #[\Override]
            public function storePreprocessedQuery(PreprocessedQueryInterface $preprocessedQuery): void
            {
                $this->queries[$preprocessedQuery->getUniqueKey()] = $preprocessedQuery;
            }
        };

        parent::setUp();
        $this->main->resolveDependencies($this->getDiContainer());
        $this->remote->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(PreprocessedQueryStorageInterface::class, $this->storage);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([
                StorageCollectionInterface::STORAGE_MAIN => $this->main,
                AqlRoundTripNote::STORAGE                => $this->remote,
            ]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->main->dispose();
        $this->remote->dispose();
        $this->dropTables();
    }

    public function testRunInsideAnExplicitTransactionRollsBackWithIt(): void
    {
        $query = $this->preprocessed(self::INSERT);

        $this->getAqlExecutor()->executeAql($query);
        $this->assertSame(1, $this->rows(), 'The first run, outside a transaction, did not insert');

        $this->runAndRollBack($query);
        $this->assertSame(1, $this->rows(), 'The run inside the rolled back transaction was kept');

        (new WithTransaction($this->getAqlExecutor()))->run(
            static fn(AqlExecutorInterface $executor) => $executor->executeAql($query)
        );
        $this->assertSame(2, $this->rows(), 'The run inside the committed transaction was lost');

        $this->getAqlExecutor()->executeAql($query);
        $this->assertSame(3, $this->rows());
    }

    public function testTheTransactionOfTheFirstRunDoesNotStayWithTheQuery(): void
    {
        $query = $this->preprocessed(self::INSERT);

        $this->runAndRollBack($query);
        $this->assertSame(0, $this->rows());

        $this->getAqlExecutor()->executeAql($query);
        $this->assertSame(1, $this->rows(), 'The run after the rolled back one did not commit by itself');
    }

    public function testAStoredQueryIsReusedByKey(): void
    {
        $this->getAqlExecutor()->executeAql($this->preprocessed(self::INSERT, 'insert'));
        $this->getAqlExecutor()->executeAql($this->preprocessed('INSERT INTO AqlRoundTrip SET value = "other"', 'insert'));

        $this->assertSame(['preprocessed', 'preprocessed'], $this->values());
    }

    public function testQueriesWithoutAKeyAreNotStored(): void
    {
        $this->getAqlExecutor()->executeAql(new PreprocessedQuery(
            static fn() => (new AqlParser())->parse(self::INSERT)
        ));
        $this->getAqlExecutor()->executeAql(new PreprocessedQuery(
            static fn() => (new AqlParser())->parse('INSERT INTO AqlRoundTrip SET value = "other"')
        ));

        $this->assertSame(['preprocessed', 'other'], $this->values());
        $this->assertSame([], $this->storage->queries);
    }

    public function testAQueryThatFailsToPreprocessIsNotStored(): void
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $error = null;

            try {
                $this->getAqlExecutor()->executeAql($this->preprocessed('SELECT missing FROM AqlRoundTrip'));
            } catch (\Throwable $throwable) {
                $error = $throwable;
            }

            $this->assertInstanceOf(PropertyNotFound::class, $error, 'Attempt ' . $attempt . ': the query ran');
            $this->assertStringNotContainsString('not defined', $error->getMessage(), 'Attempt ' . $attempt);
        }

        $this->assertSame([], $this->storage->queries);
    }

    public function testAnOverlappingRunUsesItsOwnCopy(): void
    {
        $this->pdo->exec('INSERT INTO aql_round_trip (id, value) VALUES (1, "locked")');
        $query = $this->preprocessed('UPDATE AqlRoundTrip SET value = CONCAT(value, "+") WHERE id = 1');
        $this->getAqlExecutor()->executeAql($query);

        $this->pdo->beginTransaction();
        $this->pdo->query('SELECT id FROM aql_round_trip WHERE id = 1 FOR UPDATE')->fetchAll();
        $first = spawn(fn() => $this->getAqlExecutor()->executeAql($query));
        delay(50);
        $second = spawn(fn() => $this->getAqlExecutor()->executeAql($query));
        delay(50);
        $this->pdo->commit();

        await($first);
        await($second);
        $this->assertSame(['locked+++'], $this->values());
    }

    /**
     * A prepared statement belongs to one connection and is refused at preprocessing; the refused query is neither
     * marked preprocessed nor stored, so a retry is refused again.
     */
    public function testARefusedQueryIsRefusedAgainOnRetry(): void
    {
        $query = new PreprocessedQuery(static function (): BasicQueryInterface {
            $select = (new AqlParser())->parse('SELECT value FROM AqlRoundTrip');
            $select->getQueryOptions()->addOption(new QueryOption(QueryInterface::PREPARING));

            return $select;
        }, 'prepared');

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->getAqlExecutor()->executeAql($query);
                $this->fail('A prepared statement ran on attempt ' . $attempt);
            } catch (QueryException) {
            }
        }

        $this->assertFalse($query->wasPreprocessed());
        $this->assertSame([], $this->storage->queries);
    }

    public function testAnOverlappingRunKeepsItsTransaction(): void
    {
        $this->pdo->exec('INSERT INTO aql_round_trip (id, value) VALUES (1, "locked")');
        $query = $this->preprocessed('UPDATE AqlRoundTrip SET value = CONCAT(value, "+") WHERE id = 1');
        $this->getAqlExecutor()->executeAql($query);

        $this->pdo->beginTransaction();
        $this->pdo->query('SELECT id FROM aql_round_trip WHERE id = 1 FOR UPDATE')->fetchAll();
        $first = spawn(fn() => $this->getAqlExecutor()->executeAql($query));
        delay(50);
        $second = spawn(fn() => $this->runAndRollBack($query));
        delay(50);
        $this->pdo->commit();
        await($first);
        await($second);

        $this->assertSame(['locked++'], $this->values(), 'The overlapping run left its transaction');
    }

    public function testAnAdditionalHandlerIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->getAqlExecutor()->executeAql(
            $this->preprocessed(self::INSERT), new OptionsWithAdditionalHandler([], static fn() => null)
        );
    }

    public function testAdditionalOptionsAreRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->getAqlExecutor()->executeAql($this->preprocessed(self::INSERT), new AdditionalOptions(['tenant' => 'b']));
    }

    private function preprocessed(string $aql, ?string $key = null): PreprocessedQuery
    {
        return new PreprocessedQuery(static fn() => (new AqlParser())->parse($aql), $key ?? \md5($aql));
    }

    private function runAndRollBack(PreprocessedQueryInterface $query): void
    {
        try {
            (new WithTransaction($this->getAqlExecutor()))->run(static function (AqlExecutorInterface $executor) use ($query): never {
                $executor->executeAql($query);

                throw new \DomainException('roll back');
            });
        } catch (\DomainException) {
        }
    }

    private function rows(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM aql_round_trip')->fetchColumn();
    }

    /** @return list<string> */
    private function values(): array
    {
        return $this->pdo->query('SELECT value FROM aql_round_trip ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function newStorage(string $name): SqlRecordingMySql
    {
        $storage = new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 4],
        ]);
        $storage->setStorageName($name);

        return $storage;
    }

    private function dropTables(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
    }
}
