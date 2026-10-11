<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Query\Subquery;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Transaction\WithCompensatingTransaction;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;
use IfCastle\Exceptions\UnexpectedValue;
use PHPUnit\Framework\Attributes\DataProvider;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/** Parses and executes AQL on a real MariaDB table through one shared executor and PDO pool. */
class AqlMariaDbIntegrationTest extends TestCaseWithDiContainer
{
    private const string TABLE = 'aql_round_trip';

    private \PDO $pdo;

    private SqlRecordingMySql $mySql;

    private AqlExecutorInterface $aqlExecutor;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_link');
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_event');
        $this->pdo->exec('CREATE TABLE aql_round_trip_event (id INT AUTO_INCREMENT PRIMARY KEY, ownerId INT NOT NULL,'
            . ' createdAt INT NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_lease');
        $this->pdo->exec('CREATE TABLE aql_round_trip_lease (id INT AUTO_INCREMENT PRIMARY KEY, ownerId INT NOT NULL,'
            . ' expiresAt DATETIME(6) NOT NULL, windowStart DATETIME(6) NOT NULL, attempts INT NOT NULL DEFAULT 0,'
            . ' bonus INT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_round_trip_link (id INT AUTO_INCREMENT PRIMARY KEY, roundTripId INT NOT NULL, label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->mySql = new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 10],
        ]);
        $this->mySql->setStorageName(StorageCollectionInterface::STORAGE_MAIN);

        parent::setUp();
        $this->mySql->resolveDependencies($this->getDiContainer());
        $this->aqlExecutor = $this->getAqlExecutor();
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $this->mySql]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->mySql->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_event');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_lease');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_link');
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testAqlInsertAndSelectReachMariaDb(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);
        $this->assertGreaterThan(0, $id);
        $this->assertSame([['id' => $id, 'value' => 'worker-1']], $this->select($this->aqlExecutor, $id));
    }

    public function testAggregateFunctionArgumentsReachMariaDb(): void
    {
        $ids = [
            $this->insert($this->aqlExecutor, 1),
            $this->insert($this->aqlExecutor, 2),
            $this->insert($this->aqlExecutor, 3),
        ];
        $query = (new AqlParser())->parse(
            'SELECT COUNT(id) AS total, SUM(id) AS idSum FROM AqlRoundTrip'
        );
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]['total']);
        $this->assertSame(\array_sum($ids), (int) $rows[0]['idSum']);
    }

    public function testSha2OfNestedConcatPreservesUnicodeBytes(): void
    {
        $value = 'Русский café 😀';
        $insert = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (value) VALUES (?)');
        $insert->execute([$value]);
        $query = (new AqlParser())->parse("SELECT SHA2(CONCAT('html', ':', value), 256) AS digest FROM AqlRoundTrip");
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();
        $this->assertSame([['digest' => hash('sha256', 'html:' . $value)]], $rows);
    }

    public function testDescendingOrderIsAppliedByMariaDb(): void
    {
        $ids = [
            $this->insert($this->aqlExecutor, 1),
            $this->insert($this->aqlExecutor, 2),
            $this->insert($this->aqlExecutor, 3),
        ];
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip ORDER BY id DESC');
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertSame(\array_reverse($ids), \array_map('intval', \array_column($rows, 'id')));
    }

    public function testIndependentInSubqueryNeedsNoEntityRelation(): void
    {
        $first = $this->insert($this->aqlExecutor, 1);
        $second = $this->insert($this->aqlExecutor, 2);
        $this->pdo->exec('INSERT INTO aql_round_trip_link (roundTripId, label) VALUES (' . $second . ', "match")');

        $subquery = new Subquery('AqlRoundTripLink', ['roundTripId'],
            (new Where())->equal('label', new Constant('match')));
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip');
        $query->setWhere((new Where())->add(new LROperation('id', LROperationInterface::IN, $subquery)));
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertSame([['id' => $second, 'value' => 'worker-2']], $rows);
        $this->assertNotSame($first, $second);
    }

    public function testRoleJoinsAndSelfJoinReachMariaDb(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS article, article_revision');
        $this->pdo->exec('CREATE TABLE article (id INT PRIMARY KEY, name VARCHAR(64) NOT NULL,'
            . ' draftRevisionId INT NULL, publishedRevisionId INT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE article_revision (id INT PRIMARY KEY, title VARCHAR(64) NOT NULL,'
            . ' articleId INT NOT NULL, parentId INT NULL) ENGINE=InnoDB');

        try {
            $this->pdo->exec("INSERT INTO article_revision VALUES (1, 'first', 1, NULL), (2, 'second', 1, 1),"
                . " (3, 'third', 1, 2)");
            $this->pdo->exec("INSERT INTO article VALUES (1, 'one', 3, 2), (2, 'two', NULL, NULL)");

            $roles = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT a.name, p.title AS published, d.title AS draft FROM Article a'
                . ' LEFT JOIN a.publishedRevision p LEFT JOIN a.draftRevision d ORDER BY a.id'
            ))->finalize()->toArray();

            $this->assertSame([
                ['name' => 'one', 'published' => 'second', 'draft' => 'third'],
                ['name' => 'two', 'published' => null, 'draft' => null],
            ], $roles);

            $ancestors = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT r.title, p.title AS parentTitle, g.title AS grandparentTitle FROM ArticleRevision r'
                . ' JOIN r.parent p JOIN p.parent g'
            ))->finalize()->toArray();

            $this->assertSame([['title' => 'third', 'parentTitle' => 'second', 'grandparentTitle' => 'first']], $ancestors);

            $later = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT r.title, n.title AS laterTitle FROM ArticleRevision r'
                . ' LEFT JOIN ArticleRevision n ON (n.articleId = r.articleId AND n.id > r.id) ORDER BY r.id, n.id'
            ))->finalize()->toArray();

            $this->assertSame([
                ['title' => 'first', 'laterTitle' => 'second'],
                ['title' => 'first', 'laterTitle' => 'third'],
                ['title' => 'second', 'laterTitle' => 'third'],
                ['title' => 'third', 'laterTitle' => null],
            ], $later);

            // The ON predicate of a LEFT role join filters the joined rows, never the articles.
            $filtered = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT a.name, p.title FROM Article a'
                . ' LEFT JOIN a.publishedRevision p ON (p.title = "second") ORDER BY a.id'
            ))->finalize()->toArray();

            $this->assertSame([['name' => 'one', 'title' => 'second'], ['name' => 'two', 'title' => null]], $filtered);

            $none = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT a.name, p.title FROM Article a'
                . ' LEFT JOIN a.publishedRevision p ON (p.title = "first") ORDER BY a.id'
            ))->finalize()->toArray();

            $this->assertSame([['name' => 'one', 'title' => null], ['name' => 'two', 'title' => null]], $none);
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS article, article_revision');
        }
    }

    /**
     * The shape of the lib catalogue page (S25 Q3): an equality on the first column of (column, id) and a backward
     * range on id; on the full table the optimizer prefers PRIMARY there, which the hint overrides.
     */
    public function testIndexHintReachesTheMariaDbPlan(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS article');
        $this->pdo->exec('CREATE TABLE article (id INT PRIMARY KEY, name VARCHAR(64) NOT NULL,'
            . ' draftRevisionId INT NULL, publishedRevisionId INT NULL,'
            . ' KEY idx_article_draft (draftRevisionId, id)) ENGINE=InnoDB');

        try {
            $this->pdo->exec("INSERT INTO article VALUES (1, 'one', 1, NULL), (2, 'two', 2, NULL),"
                . " (3, 'three', 1, NULL), (4, 'four', 1, NULL)");

            // Without a hint MariaDB 11.4 already picks idx_article_draft here, so IGNORE shows the hint is applied.
            foreach (['FORCE' => 'idx_article_draft', 'IGNORE' => 'PRIMARY'] as $hint => $key) {
                $rows = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                    'SELECT a.id FROM Article a ' . $hint . ' INDEX (idx_article_draft)'
                    . ' WHERE a.draftRevisionId = 1 AND a.id < 4 ORDER BY a.id DESC LIMIT 20'
                ))->finalize()->toArray();

                $this->assertSame([3, 1], \array_map('intval', \array_column($rows, 'id')), $hint);

                $plan = $this->pdo->query('EXPLAIN ' . $this->mySql->lastSql)->fetchAll(\PDO::FETCH_ASSOC);

                $this->assertCount(1, $plan, $hint);
                $this->assertSame($key, $plan[0]['key'], $hint);
            }
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS article');
        }
    }

    public function testExcludedEntityKeepsRowsWhenTheSubqueryYieldsNull(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS article, article_revision');
        $this->pdo->exec('CREATE TABLE article (id INT PRIMARY KEY, name VARCHAR(64) NOT NULL,'
            . ' draftRevisionId INT NULL, publishedRevisionId INT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE article_revision (id INT PRIMARY KEY, title VARCHAR(64) NOT NULL,'
            . ' articleId INT NULL, parentId INT NULL) ENGINE=InnoDB');

        try {
            $this->pdo->exec("INSERT INTO article VALUES (1, 'one', NULL, NULL), (2, 'two', NULL, NULL)");
            // Revision 3 belongs to no article: NOT IN over its NULL articleId would return no article at all.
            $this->pdo->exec("INSERT INTO article_revision VALUES (1, 'x', 1, NULL), (2, 'y', 2, NULL), (3, 'x', NULL, NULL)");

            $names = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT a.name FROM Article a WHERE ENTITY EXCLUDE ArticleRevision(title = "x") ORDER BY a.id'
            ))->finalize()->toArray();

            $this->assertSame([['name' => 'two']], $names);
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS article, article_revision');
        }
    }

    public function testBinaryValuesRoundTripByteExact(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS aql_binary_hash');
        $this->pdo->exec('CREATE TABLE aql_binary_hash (id INT AUTO_INCREMENT PRIMARY KEY, hash BINARY(32) NOT NULL)'
            . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // 0xFF never occurs in UTF-8, and a lone 0xC3 lead byte is followed by another lead byte.
        $hash = "\xff\xfe\x00\x80'\\" . \str_repeat("\xc3", 26);
        $other = \str_repeat("\x00", 31) . "\xff";
        $this->assertSame(32, \strlen($hash));
        $this->assertFalse(\mb_check_encoding($hash, 'UTF-8'));

        try {
            foreach ([$hash, $other] as $value) {
                $insert = (new AqlParser())->parse('INSERT INTO AqlBinaryHash SET hash = {hash}');
                $insert->applyParameters(['hash' => $value]);
                $this->assertSame(1, $this->aqlExecutor->executeAql($insert)->getAffectedRows());
                $this->assertStringContainsString("X'" . \bin2hex($value) . "'", $this->mySql->lastSql);
            }

            $stored = $this->pdo->query('SELECT HEX(hash) FROM aql_binary_hash ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame(['FFFE0080275C' . \str_repeat('C3', 26), \str_repeat('00', 31) . 'FF'], $stored);

            $select = (new AqlParser())->parse('SELECT hash FROM AqlBinaryHash WHERE hash = {hash}');
            $select->applyParameters(['hash' => $hash]);
            $this->assertSame([['hash' => $hash]], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
            $this->assertStringContainsString("`hash` = X'" . \bin2hex($hash) . "'", $this->mySql->lastSql);
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS aql_binary_hash');
        }
    }

    /** MariaDB before 11.6 has no alias in a single-table DELETE, so the statement must render without one. */
    public function testSingleTableDeleteWithWhereRemovesOnlyItsRows(): void
    {
        $this->insert($this->aqlExecutor, 1);
        $this->insert($this->aqlExecutor, 2);
        $this->insert($this->aqlExecutor, 2);

        $delete = (new AqlParser())->parse('DELETE FROM AqlRoundTrip WHERE value = {value}');
        $delete->applyParameters(['value' => 'worker-2']);

        $this->assertSame(2, $this->aqlExecutor->executeAql($delete)->affected());
        $this->assertSame(
            'DELETE FROM `aql_round_trip` WHERE `aql_round_trip`.`value` = \'worker-2\'',
            $this->mySql->lastSql
        );
        $this->assertSame(['worker-1'], $this->pdo->query('SELECT value FROM ' . self::TABLE)->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * The shape of a per-owner row cap: the oldest rows of one owner go first, and a tie on the creation time is
     * broken by the key.
     */
    public function testDeleteOrderByLimitParameterRemovesTheOldestRowsOfOneOwner(): void
    {
        $ids = $this->insertEvents([[1, 50], [1, 10], [1, 30], [1, 10], [1, 20], [2, 5], [2, 6]]);

        $delete = (new AqlParser())->parse(
            'DELETE FROM AqlRoundTripEvent WHERE ownerId = {id} ORDER BY createdAt, id LIMIT {n}'
        );
        $delete->applyParameters(['id' => 1, 'n' => 3]);

        $this->assertSame(3, $this->aqlExecutor->executeAql($delete)->affected());
        $this->assertSame(
            'DELETE FROM `aql_round_trip_event` WHERE `aql_round_trip_event`.`ownerId` = 1'
            . ' ORDER BY `aql_round_trip_event`.`createdAt`, `aql_round_trip_event`.`id` LIMIT 3',
            $this->mySql->lastSql
        );

        // Created at 10, 10 (both ties go by id) and 20 are gone; 30 and 50 of owner 1 and both rows of owner 2 stay.
        $this->assertSame(
            [$ids[0], $ids[2], $ids[5], $ids[6]],
            \array_map('intval', $this->pdo->query('SELECT id FROM aql_round_trip_event ORDER BY id')
                ->fetchAll(\PDO::FETCH_COLUMN))
        );
    }

    public function testDeleteWithoutLimitValueDeletesNothing(): void
    {
        $this->insertEvents([[1, 10], [1, 20]]);

        $delete = (new AqlParser())->parse('DELETE FROM AqlRoundTripEvent WHERE ownerId = 1 ORDER BY id LIMIT {n}');

        try {
            $this->aqlExecutor->executeAql($delete);
            $this->fail('The DELETE ran with an unbound LIMIT parameter');
        } catch (\Throwable) {
            $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM aql_round_trip_event')->fetchColumn());
        }
    }

    public function testSelectLimitParameterReturnsThatManyRows(): void
    {
        $this->insertEvents([[1, 50], [1, 10], [1, 30], [1, 20]]);

        $select = (new AqlParser())->parse('SELECT createdAt FROM AqlRoundTripEvent ORDER BY createdAt LIMIT {n}');

        foreach ([2, 3] as $count) {
            $select->applyParameters(['n' => $count]);
            $rows = $this->aqlExecutor->executeAql($select)->finalize()->toArray();

            $this->assertSame([10, 20, 30, 50][$count - 1], (int) \end($rows)['createdAt']);
            $this->assertCount($count, $rows);
        }
    }

    public function testLimitParameterRejectsANonIntegerValue(): void
    {
        $this->insertEvents([[1, 10], [1, 20]]);

        $delete = (new AqlParser())->parse('DELETE FROM AqlRoundTripEvent WHERE ownerId = 1 LIMIT {n}');
        $delete->applyParameters(['n' => '1']);

        try {
            $this->aqlExecutor->executeAql($delete);
            $this->fail('A string LIMIT value was accepted');
        } catch (UnexpectedValue) {
            $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM aql_round_trip_event')->fetchColumn());
        }
    }

    public function testUtcTimestampSelectsRowsByTheServerClock(): void
    {
        $this->insertLeases([[1, '-1 hour'], [2, '+1 hour'], [3, '+2 hours']]);

        foreach (['UTC_TIMESTAMP(6)', 'UTC_TIMESTAMP()'] as $call) {
            $select = (new AqlParser())->parse(
                'SELECT ownerId FROM AqlRoundTripLease WHERE expiresAt > ' . $call . ' ORDER BY ownerId'
            );

            $this->assertSame(
                [2, 3],
                \array_map('intval', \array_column($this->aqlExecutor->executeAql($select)->finalize()->toArray(), 'ownerId')),
                $call
            );
            $this->assertStringContainsString('> ' . $call, $this->mySql->lastSql);
        }
    }

    public function testWhereWithNullFindsRowsWithoutAValue(): void
    {
        $this->insertLeases([[1, '+1 hour', null], [2, '+1 hour', 5], [3, '+1 hour', null]]);

        $select = (new AqlParser())->parse('SELECT ownerId FROM AqlRoundTripLease ORDER BY ownerId');
        $select->setWhere(new Where());
        $select->where('bonus', null);

        $this->assertSame(
            [1, 3],
            \array_map('intval', \array_column($this->aqlExecutor->executeAql($select)->finalize()->toArray(), 'ownerId'))
        );
        $this->assertStringContainsString('`bonus` IS NULL', $this->mySql->lastSql);
    }

    public function testCoalesceKeepsItsZeroFallback(): void
    {
        $this->insertLeases([[1, '+1 hour', null], [2, '+1 hour', 5]]);

        $select = (new AqlParser())->parse(
            'SELECT ownerId, COALESCE(bonus, 0) AS bonus FROM AqlRoundTripLease ORDER BY ownerId'
        );
        $rows = $this->aqlExecutor->executeAql($select)->finalize()->toArray();

        $this->assertSame([[1, 0], [2, 5]], \array_map(
            static fn(array $row): array => [(int) $row['ownerId'], (int) $row['bonus']],
            $rows
        ));
        $this->assertStringContainsString('COALESCE(`t0`.`bonus`, 0)', $this->mySql->lastSql);
    }

    /**
     * @param list<array{0: int, 1: string, 2?: int|null}> $leases owner id, expiry as a relative time string
     *                                                            and the optional bonus of each row
     */
    private function insertLeases(array $leases): void
    {
        $insert = $this->pdo->prepare('INSERT INTO aql_round_trip_lease (ownerId, expiresAt, windowStart, bonus)'
            . ' VALUES (?, ?, ?, ?)');

        foreach ($leases as $lease) {
            $expiresAt = (new \DateTimeImmutable($lease[1], new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $insert->execute([$lease[0], $expiresAt, $expiresAt, $lease[2] ?? null]);
        }
    }

    /**
     * @param list<array{0: int, 1: int}> $events owner id and creation time of each row
     * @return list<int> the generated ids, in the order of $events
     */
    private function insertEvents(array $events): array
    {
        $insert = $this->pdo->prepare('INSERT INTO aql_round_trip_event (ownerId, createdAt) VALUES (?, ?)');
        $ids = [];

        foreach ($events as [$ownerId, $createdAt]) {
            $insert->execute([$ownerId, $createdAt]);
            $ids[] = (int) $this->pdo->lastInsertId();
        }

        return $ids;
    }

    public function testNestedAndOrFiltersCannotBypassOtherPredicates(): void
    {
        $first = $this->insert($this->aqlExecutor, 1);
        $second = $this->insert($this->aqlExecutor, 2);
        $this->insert($this->aqlExecutor, 3);

        $select = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip');
        $select->setWhere((new Where())->equal('id', new Constant($first))
            ->add((new Conditions(Conditions::TYPE_OR))
                ->equal('value', new Constant('worker-2'))
                ->equal('value', new Constant('worker-3'))));
        $this->assertSame([], $this->aqlExecutor->executeAql($select)->finalize()->toArray());

        $select = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip');
        $select->setWhere((new Where())
            ->add((new Conditions(Conditions::TYPE_OR))
                ->equal('id', new Constant($first))
                ->equal('id', new Constant($second)))
            ->equal('value', new Constant('worker-2')));
        $this->assertSame([['id' => $second, 'value' => 'worker-2']],
            $this->aqlExecutor->executeAql($select)->finalize()->toArray());
    }

    public function testTenOverlappingCoroutinesInsertAndReadTheirOwnRows(): void
    {
        $workers = [];

        for ($worker = 1; $worker <= 10; $worker++) {
            $workers[] = spawn(function () use ($worker): array {
                $id = $this->insert($this->aqlExecutor, $worker);
                delay(20);

                return [$id, $this->select($this->aqlExecutor, $id)];
            });
        }

        $ids = [];
        foreach ($this->awaitAll($workers) as $index => [$id, $rows]) {
            $this->assertGreaterThan(0, $id);
            $this->assertSame([['id' => $id, 'value' => 'worker-' . ($index + 1)]], $rows);
            $ids[] = $id;
        }

        $this->assertCount(10, \array_unique($ids));
        $this->assertCount(10, $this->pdo->query('SELECT id FROM ' . self::TABLE)->fetchAll());
    }

    public static function transactionWrappers(): array
    {
        return [
            'ordinary' => [WithTransaction::class],
            'compensating' => [WithCompensatingTransaction::class],
        ];
    }

    #[DataProvider('transactionWrappers')]
    public function testWithTransactionCommitsAndRollsBackRealAqlQueries(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $committedId = $transactional->run(function (AqlExecutorInterface $executor): int {
            $id = $this->insert($executor, 101);
            $this->assertSame([['id' => $id, 'value' => 'worker-101']], $this->select($executor, $id));
            return $id;
        });

        $rolledBackId = null;
        try {
            $transactional->run(function (AqlExecutorInterface $executor) use (&$rolledBackId): never {
                $rolledBackId = $this->insert($executor, 102);
                $this->assertSame([['id' => $rolledBackId, 'value' => 'worker-102']], $this->select($executor, $rolledBackId));
                throw new \RuntimeException('roll back this row');
            });
            $this->fail('The transaction callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('roll back this row', $exception->getMessage());
        }

        $this->assertSame([['id' => $committedId, 'value' => 'worker-101']], $this->select($this->aqlExecutor, $committedId));
        $this->assertNotNull($rolledBackId);
        $this->assertSame([], $this->select($this->aqlExecutor, $rolledBackId));
    }

    #[DataProvider('transactionWrappers')]
    public function testNestedTransactionsKeepOuterChangesAfterChildRollback(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $outerId = null;
        $childId = null;
        $afterChildId = null;

        $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use (&$outerId, &$childId, &$afterChildId): void {
            $outerId = $this->insert($outer, 201);

            try {
                $outer->run(function (AqlExecutorInterface $child) use (&$childId): never {
                    $childId = $this->insert($child, 202);
                    throw new \RuntimeException('roll back child');
                });
                $this->fail('The child callback did not throw');
            } catch (\RuntimeException $exception) {
                $this->assertSame('roll back child', $exception->getMessage());
            }

            $afterChildId = $this->insert($outer, 203);
        });

        $this->assertNotNull($outerId);
        $this->assertNotNull($childId);
        $this->assertNotNull($afterChildId);
        $this->assertSame([['id' => $outerId, 'value' => 'worker-201']], $this->select($this->aqlExecutor, $outerId));
        $this->assertSame([], $this->select($this->aqlExecutor, $childId));
        $this->assertSame([['id' => $afterChildId, 'value' => 'worker-203']], $this->select($this->aqlExecutor, $afterChildId));
    }

    #[DataProvider('transactionWrappers')]
    public function testOuterRollbackUndoesCommittedChild(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $outerId = null;
        $childId = null;

        try {
            $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use (&$outerId, &$childId): never {
                $outerId = $this->insert($outer, 301);
                $outer->run(function (AqlExecutorInterface $child) use (&$childId): void {
                    $childId = $this->insert($child, 302);
                });
                throw new \RuntimeException('roll back outer');
            });
            $this->fail('The outer callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('roll back outer', $exception->getMessage());
        }

        $this->assertNotNull($outerId);
        $this->assertNotNull($childId);
        $this->assertSame([], $this->select($this->aqlExecutor, $outerId));
        $this->assertSame([], $this->select($this->aqlExecutor, $childId));
    }

    #[DataProvider('transactionWrappers')]
    public function testConcurrentRunsOnOneWrapperKeepTransactionsIndependent(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $workers = [];

        for ($worker = 401; $worker <= 410; $worker++) {
            $workers[] = spawn(function () use ($transactional, $worker): array {
                return $transactional->run(function (AqlExecutorInterface $executor) use ($worker): array {
                    $id = $this->insert($executor, $worker);
                    delay(20);

                    return [$id, $this->select($executor, $id)];
                });
            });
        }

        $ids = [];
        foreach ($this->awaitAll($workers) as $index => [$id, $rows]) {
            $this->assertGreaterThan(0, $id);
            $this->assertSame([['id' => $id, 'value' => 'worker-' . (401 + $index)]], $rows);
            $ids[] = $id;
        }

        $this->assertCount(10, \array_unique($ids));
        $this->assertCount(10, $this->pdo->query('SELECT id FROM ' . self::TABLE)->fetchAll());
    }

    #[DataProvider('transactionWrappers')]
    public function testConcurrentCommitChildRollbackAndThrowAreIndependent(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $workers = [];

        for ($worker = 1; $worker <= 10; $worker++) {
            $workers[] = spawn(function () use ($transactional, $worker): array {
                return match ($worker % 3) {
                    1 => $transactional->run(function (AqlExecutorInterface $executor) use ($worker): array {
                        $id = $this->insert($executor, $worker);
                        delay(20);
                        $this->assertSame([['id' => $id, 'value' => 'worker-' . $worker]], $this->select($executor, $id));
                        return ['commit', $id];
                    }),
                    2 => $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use ($worker): array {
                        $id = null;
                        try {
                            $outer->run(function (AqlExecutorInterface $child) use ($worker, &$id): never {
                                $id = $this->insert($child, $worker);
                                delay(20);
                                throw new \RuntimeException('roll back child ' . $worker);
                            });
                            $this->fail('The child callback did not throw');
                        } catch (\RuntimeException $exception) {
                            $this->assertSame('roll back child ' . $worker, $exception->getMessage());
                        }
                        return ['rollback', $id];
                    }),
                    default => $this->runThrowingWorker($transactional, $worker),
                };
            });
        }

        $ids = [];
        $committed = [];
        foreach ($this->awaitAll($workers) as $index => [$outcome, $id]) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
            $ids[] = $id;
            if ($outcome === 'commit') {
                $committed[] = ['id' => $id, 'value' => 'worker-' . ($index + 1)];
            }
        }

        $this->assertCount(10, \array_unique($ids));
        \usort($committed, static fn(array $left, array $right): int => $left['id'] <=> $right['id']);
        $actual = await(spawn(function (): array {
            $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip ORDER BY id');
            return $this->aqlExecutor->executeAql($query)->finalize()->toArray();
        }));
        $this->assertSame($committed, $actual);
    }

    private function runThrowingWorker(
        WithTransaction|WithCompensatingTransaction $transactional,
        int $worker,
    ): array {
        $id = null;
        try {
            $transactional->run(function (AqlExecutorInterface $executor) use ($worker, &$id): never {
                $id = $this->insert($executor, $worker);
                delay(20);
                throw new \RuntimeException('fail outer ' . $worker);
            });
            $this->fail('The outer callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fail outer ' . $worker, $exception->getMessage());
        }

        return ['throw', $id];
    }

    private function insert(AqlExecutorInterface $executor, int $worker): int
    {
        $query = (new AqlParser())->parse('INSERT INTO AqlRoundTrip SET value = "worker-' . $worker . '"');
        $result = $executor->executeAql($query);
        $this->assertSame(1, $result->getAffectedRows());
        $key = $result->getLastPrimaryKey();

        if (!\is_array($key) || !isset($key['id'])) {
            throw new \LogicException('The AQL insert did not return its generated id');
        }

        return (int) $key['id'];
    }

    private function select(AqlExecutorInterface $executor, int $id): array
    {
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip WHERE id = ' . $id);

        return $executor->executeAql($query)->finalize()->toArray();
    }

    /** @param list<\Async\Coroutine> $workers */
    private function awaitAll(array $workers): array
    {
        $results = [];
        $firstError = null;

        foreach ($workers as $index => $worker) {
            try {
                $results[$index] = await($worker);
            } catch (\Throwable $exception) {
                $firstError ??= $exception;
            }
        }

        if ($firstError !== null) {
            throw $firstError;
        }

        return $results;
    }
}
