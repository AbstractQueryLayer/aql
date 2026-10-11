<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\PostgreSql\Storage\PostgreSql;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/** A locking read on a real PostgreSQL: `FOR UPDATE` as written, a shared lock as `FOR SHARE`. */
final class PostgreSqlLockingReadTest extends TestCaseWithDiContainer
{
    // https://www.postgresql.org/docs/current/errcodes-appendix.html : lock_not_available
    private const string LOCK_NOT_AVAILABLE = '55P03';

    private \PDO $pdo;

    private PostgreSql $storage;

    private string $lastSql         = '';

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = PostgresServer::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
        $this->pdo->exec('CREATE TABLE aql_round_trip (id SERIAL PRIMARY KEY, value VARCHAR(64) NOT NULL)');
        $this->pdo->exec("INSERT INTO aql_round_trip (value) VALUES ('first')");

        $test                       = $this;
        $this->storage              = new class ([
            'dsn'                   => PostgresServer::dsn(),
            'username'              => PostgresServer::user(),
            'password'              => PostgresServer::password(),
        ], static function (string $sql) use ($test): void {
            $test->lastSql          = $sql;
        }) extends PostgreSql {
            public function __construct(array $config, private readonly \Closure $record)
            {
                parent::__construct($config);
            }

            #[\Override]
            public function executeSql(string $sql, ?object $context = null): ResultInterface
            {
                ($this->record)($sql);

                return parent::executeSql($sql, $context);
            }
        };
        $this->storage->setStorageName(StorageCollectionInterface::STORAGE_MAIN);

        parent::setUp();
        $this->storage->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $this->storage]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->storage->disconnect();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
    }

    public static function lockClauses(): array
    {
        return [
            'for update' => ['FOR UPDATE', 'FOR UPDATE'],
            'share mode' => ['LOCK IN SHARE MODE', 'FOR SHARE'],
        ];
    }

    #[DataProvider('lockClauses')]
    public function testLockingReadBlocksAnotherConnectionUntilCommit(string $aqlClause, string $sqlClause): void
    {
        $other                      = PostgresServer::pdo();
        $other->exec("SET lock_timeout = '1s'");
        $update                     = "UPDATE aql_round_trip SET value = 'second' WHERE id = 1";

        (new WithTransaction($this->getAqlExecutor()))->run(
            function (AqlExecutorInterface $executor) use ($aqlClause, $sqlClause, $other, $update): void {
                $select             = (new AqlParser())->parse(
                    'SELECT id FROM AqlRoundTrip WHERE id = 1 ' . $aqlClause
                );

                $this->assertSame([['id' => 1]], $executor->executeAql($select)->finalize()->toArray());
                $this->assertStringEndsWith(' ' . $sqlClause, $this->lastSql);

                try {
                    $other->exec($update);
                    $this->fail('The UPDATE of a locked row did not wait');
                } catch (\PDOException $exception) {
                    $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->errorInfo[0]);
                }
            }
        );

        $this->assertSame(1, $other->exec($update));
    }

    /** Control: that the other connection waits above is caused by the lock, not by the transaction itself. */
    public function testPlainReadInATransactionDoesNotBlockAnotherConnection(): void
    {
        $other                      = PostgresServer::pdo();
        $other->exec("SET lock_timeout = '1s'");

        (new WithTransaction($this->getAqlExecutor()))->run(
            function (AqlExecutorInterface $executor) use ($other): void {
                $select             = (new AqlParser())->parse('SELECT id FROM AqlRoundTrip WHERE id = 1');

                $this->assertSame([['id' => 1]], $executor->executeAql($select)->finalize()->toArray());
                $this->assertStringNotContainsString(' FOR ', $this->lastSql);

                $this->assertSame(1, $other->exec("UPDATE aql_round_trip SET value = 'plain' WHERE id = 1"));
            }
        );
    }
}
