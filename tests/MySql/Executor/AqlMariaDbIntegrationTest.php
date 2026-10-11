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
use IfCastle\AQL\Executor\Exceptions\QueryException;
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

    // https://mariadb.com/kb/en/mariadb-error-code-reference/ : Lock wait timeout exceeded
    private const int ER_LOCK_WAIT_TIMEOUT = 1205;

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
            . ' bonus INT NULL, UNIQUE KEY uniq_owner (ownerId)) ENGINE=InnoDB');
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
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_label');
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testAqlInsertAndSelectReachMariaDb(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);
        $this->assertGreaterThan(0, $id);
        $this->assertSame([['id' => $id, 'value' => 'worker-1']], $this->select($this->aqlExecutor, $id));
    }

    public function testInsertValuesWithoutTheKeyReturnsTheGeneratedId(): void
    {
        $query = (new AqlParser())->parse('INSERT INTO AqlRoundTrip (value) VALUES ({value})');
        $query->applyParameters(['value' => 'by-values']);
        $key = $this->aqlExecutor->executeAql($query)->getLastPrimaryKey();

        $this->assertIsArray($key);
        $this->assertSame([['id' => (int) $key['id'], 'value' => 'by-values']],
            $this->select($this->aqlExecutor, (int) $key['id']));
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
     * A to-many role over two column pairs joins only the pages of the volume's published issue: pages of its other
     * issues and pages of another volume in the same issue number stay out, and a volume without one keeps its row.
     */
    public function testCompositeRoleJoinsOnlyRowsMatchingEveryPair(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS volume, volume_page');
        $this->pdo->exec('CREATE TABLE volume (id INT PRIMARY KEY, name VARCHAR(64) NOT NULL,'
            . ' publishedIssueId INT NULL, firstPageNo INT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE volume_page (issueId INT NOT NULL, volumeId INT NOT NULL, pageNo INT NOT NULL,'
            . ' title VARCHAR(64) NOT NULL, PRIMARY KEY (issueId, volumeId, pageNo)) ENGINE=InnoDB');

        try {
            $this->pdo->exec("INSERT INTO volume VALUES (1, 'one', 10, 1), (2, 'two', 11, NULL),"
                . " (3, 'three', NULL, NULL)");
            $this->pdo->exec("INSERT INTO volume_page VALUES (10, 1, 1, 'one-10-1'), (10, 1, 2, 'one-10-2'),"
                . " (9, 1, 1, 'one-9-1'), (10, 2, 1, 'two-10-1'), (11, 2, 1, 'two-11-1')");

            $rows = $this->aqlExecutor->executeAql((new AqlParser())->parse(
                'SELECT v.name, p.title FROM Volume v LEFT JOIN v.publishedPages p ORDER BY v.id, p.pageNo'
            ))->finalize()->toArray();

            $this->assertSame([
                ['name' => 'one', 'title' => 'one-10-1'],
                ['name' => 'one', 'title' => 'one-10-2'],
                ['name' => 'two', 'title' => 'two-11-1'],
                ['name' => 'three', 'title' => null],
            ], $rows);

            $plan = $this->pdo->query('EXPLAIN ' . $this->mySql->lastSql)->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertSame(['ref', 'PRIMARY'], [$plan[1]['type'], $plan[1]['key']], \json_encode($plan));
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS volume, volume_page');
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
                $this->ownerIds($this->aqlExecutor->executeAql($select)->finalize()->toArray()),
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
            $this->ownerIds($this->aqlExecutor->executeAql($select)->finalize()->toArray())
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

    public function testInsertValuesAcceptExpressionsAndIntervalArithmetic(): void
    {
        $before = $this->serverUtcNow();

        $insert = (new AqlParser())->parse(
            'INSERT INTO AqlRoundTripLease (ownerId, windowStart, expiresAt, attempts)'
            . ' VALUES ({owner}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) + INTERVAL {seconds} SECOND, 1 + 1)'
        );
        $insert->applyParameters(['owner' => 7, 'seconds' => 90]);

        $this->assertSame(1, $this->aqlExecutor->executeAql($insert)->getAffectedRows());
        $this->assertStringContainsString(
            'VALUES (7,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) + INTERVAL 90 SECOND,1 + 1)',
            $this->mySql->lastSql
        );

        $after = $this->serverUtcNow();
        $row = $this->pdo->query('SELECT windowStart, expiresAt, attempts FROM aql_round_trip_lease')
            ->fetch(\PDO::FETCH_ASSOC);

        $windowStart = $this->microtime($row['windowStart']);
        $this->assertGreaterThanOrEqual($before - 0.001, $windowStart);
        $this->assertLessThanOrEqual($after + 0.001, $windowStart);
        $this->assertEqualsWithDelta(90.0, $this->microtime($row['expiresAt']) - $windowStart, 0.001);
        $this->assertSame(2, (int) $row['attempts']);
    }

    public function testInsertMultipleRowsOfValuesKeepsConstantsAndExpressions(): void
    {
        $insert = (new AqlParser())->parse(
            'INSERT INTO AqlRoundTripLease (ownerId, windowStart, expiresAt, attempts) VALUES'
            . ' (1, "2026-01-01 00:00:00", "2026-01-02 00:00:00", 3), (2, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 4 - 1)'
        );

        $this->assertSame(2, $this->aqlExecutor->executeAql($insert)->getAffectedRows());

        $rows = $this->pdo->query('SELECT ownerId, attempts, windowStart FROM aql_round_trip_lease ORDER BY ownerId')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertSame([1, 3], [(int) $rows[0]['ownerId'], (int) $rows[0]['attempts']]);
        $this->assertSame('2026-01-01 00:00:00', \substr($rows[0]['windowStart'], 0, 19));
        $this->assertSame([2, 3], [(int) $rows[1]['ownerId'], (int) $rows[1]['attempts']]);
        $this->assertEqualsWithDelta($this->serverUtcNow(), $this->microtime($rows[1]['windowStart']), 5.0);
    }

    public function testWhereComparesAnIntervalShiftedColumnWithTheServerClock(): void
    {
        $this->insertLeases([[1, '-120 seconds'], [2, '-30 seconds'], [3, '-61 seconds'], [4, '+10 seconds']]);

        $select = (new AqlParser())->parse(
            'SELECT ownerId FROM AqlRoundTripLease WHERE windowStart + INTERVAL {seconds} SECOND <= UTC_TIMESTAMP(6)'
            . ' ORDER BY ownerId'
        );
        $select->applyParameters(['seconds' => 60]);

        $this->assertSame(
            [1, 3],
            $this->ownerIds($this->aqlExecutor->executeAql($select)->finalize()->toArray())
        );
        $this->assertStringContainsString(
            '`t0`.`windowStart` + INTERVAL 60 SECOND <= UTC_TIMESTAMP(6)',
            $this->mySql->lastSql
        );
    }

    public function testSelectColumnAndUpdateSetAcceptIntervalAndArithmetic(): void
    {
        $this->insertLeases([[1, '+1 hour', 5], [2, '+1 hour', 5]]);

        $select = (new AqlParser())->parse(
            'SELECT expiresAt - INTERVAL {minutes} MINUTE AS earlier, bonus + 1 AS next FROM AqlRoundTripLease'
            . ' WHERE ownerId = 1'
        );
        $select->applyParameters(['minutes' => 30]);
        $row = $this->aqlExecutor->executeAql($select)->finalize()->toArray()[0];
        $expiresAt = $this->microtime($this->pdo->query('SELECT expiresAt FROM aql_round_trip_lease WHERE ownerId = 1')
            ->fetchColumn());

        $this->assertEqualsWithDelta($expiresAt - 1800.0, $this->microtime($row['earlier']), 0.001);
        $this->assertSame(6, (int) $row['next']);

        $update = (new AqlParser())->parse(
            'UPDATE AqlRoundTripLease SET bonus = bonus + 10, expiresAt = UTC_TIMESTAMP(6) + INTERVAL {days} DAY'
            . ' WHERE ownerId = 2'
        );
        $update->applyParameters(['days' => 2]);
        $before = $this->serverUtcNow();

        $this->assertSame(1, $this->aqlExecutor->executeAql($update)->getAffectedRows());

        $after = $this->serverUtcNow();
        $row = $this->pdo->query('SELECT bonus, expiresAt FROM aql_round_trip_lease WHERE ownerId = 2')
            ->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(15, (int) $row['bonus']);
        $this->assertGreaterThanOrEqual($before + 2 * 86400 - 0.001, $this->microtime($row['expiresAt']));
        $this->assertLessThanOrEqual($after + 2 * 86400 + 0.001, $this->microtime($row['expiresAt']));
    }

    /** The shape of a counter inside a time window: the counter restarts once the window is over. */
    public function testOnDuplicateKeyUpdateRestartsTheCounterAfterTheWindow(): void
    {
        $this->insertLeases([[1, '+1 hour', null, '-120 seconds', 5], [2, '+1 hour', null, '-10 seconds', 5]]);

        foreach ([1, 2] as $owner) {
            $upsert = (new AqlParser())->parse(
                'INSERT INTO AqlRoundTripLease (ownerId, expiresAt, windowStart, attempts)'
                . ' VALUES ({owner}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 1) ON DUPLICATE KEY UPDATE'
                . ' attempts = IF(windowStart + INTERVAL {seconds} SECOND <= UTC_TIMESTAMP(6), 1, attempts + 1),'
                . ' windowStart = IF(windowStart + INTERVAL {seconds} SECOND <= UTC_TIMESTAMP(6),'
                . ' UTC_TIMESTAMP(6), windowStart)'
            );
            $upsert->applyParameters(['owner' => $owner, 'seconds' => 60]);
            $this->aqlExecutor->executeAql($upsert);
        }

        $this->assertStringContainsString(
            'ON DUPLICATE KEY UPDATE `attempts` = IF(`windowStart` + INTERVAL 60 SECOND <= UTC_TIMESTAMP(6), 1,'
            . ' `attempts` + 1)',
            $this->mySql->lastSql
        );

        $rows = $this->pdo->query('SELECT ownerId, attempts, windowStart FROM aql_round_trip_lease ORDER BY ownerId')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertSame([1, 6], [(int) $rows[0]['attempts'], (int) $rows[1]['attempts']]);
        $this->assertEqualsWithDelta($this->serverUtcNow(), $this->microtime($rows[0]['windowStart']), 5.0);
        $this->assertEqualsWithDelta($this->serverUtcNow() - 10, $this->microtime($rows[1]['windowStart']), 5.0);
    }

    /**
     * The lock-wait timeout of the second connection is one second, so a blocked UPDATE fails fast with error 1205.
     * That the AQL query ran inside the transaction follows from the controls: the same locking read in autocommit
     * is refused by the executor (its lock would end with the statement), and an ordinary read in the transaction
     * holds none, so only a locking read inside the transaction can make the second connection wait.
     */
    public function testForUpdateInsideATransactionBlocksAnotherConnectionUntilCommit(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);
        $other = $this->secondConnection();
        $transactional = new WithTransaction($this->aqlExecutor);

        // An ordinary read in a transaction takes no row lock.
        $transactional->run(function (AqlExecutorInterface $executor) use ($id, $other): void {
            $this->assertSame([['id' => $id]], $this->lockingSelect($executor, $id, ''));
            $this->assertSame(1, $other->exec('UPDATE ' . self::TABLE . ' SET value = "plain" WHERE id = ' . $id));
        });

        // The same locking read outside a transaction would release its lock when the statement ends: it is refused.
        $lastSql = $this->mySql->lastSql;

        try {
            $this->lockingSelect($this->aqlExecutor, $id, ' FOR UPDATE');
            $this->fail('A locking read outside a transaction was executed');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('needs a transaction', $exception->getMessage());
        }

        $this->assertSame($lastSql, $this->mySql->lastSql);
        $this->assertSame(1, $other->exec('UPDATE ' . self::TABLE . ' SET value = "autocommit" WHERE id = ' . $id));

        $transactional->run(function (AqlExecutorInterface $executor) use ($id, $other): void {
            $this->assertSame([['id' => $id]], $this->lockingSelect($executor, $id, ' FOR UPDATE'));
            $this->assertStringEndsWith(' FOR UPDATE', $this->mySql->lastSql);

            try {
                $other->exec('UPDATE ' . self::TABLE . ' SET value = "blocked" WHERE id = ' . $id);
                $this->fail('The UPDATE of a locked row did not wait');
            } catch (\PDOException $exception) {
                $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $exception->errorInfo[1]);
            }
        });

        $this->assertSame(1, $other->exec('UPDATE ' . self::TABLE . ' SET value = "after commit" WHERE id = ' . $id));
        $this->assertSame(
            ['after commit'],
            $this->pdo->query('SELECT value FROM ' . self::TABLE . ' WHERE id = ' . $id)->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    public function testLockInShareModeBlocksWritersButNotReaders(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);
        $other = $this->secondConnection();

        $transactional = new WithTransaction($this->aqlExecutor);

        $transactional->run(function (AqlExecutorInterface $executor) use ($id, $other): void {
            $this->assertSame([['id' => $id]], $this->lockingSelect($executor, $id, ' LOCK IN SHARE MODE'));
            $this->assertStringEndsWith(' LOCK IN SHARE MODE', $this->mySql->lastSql);

            $this->assertSame(
                ['worker-1'],
                $other->query('SELECT value FROM ' . self::TABLE . ' WHERE id = ' . $id)->fetchAll(\PDO::FETCH_COLUMN)
            );

            try {
                $other->exec('UPDATE ' . self::TABLE . ' SET value = "blocked" WHERE id = ' . $id);
                $this->fail('The UPDATE of a share-locked row did not wait');
            } catch (\PDOException $exception) {
                $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $exception->errorInfo[1]);
            }
        });
    }

    public function testForUpdateRendersAfterLimit(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);

        $select = (new AqlParser())->parse(
            'SELECT id FROM AqlRoundTrip WHERE id = {id} ORDER BY id LIMIT {n} FOR UPDATE'
        );
        $select->applyParameters(['id' => $id, 'n' => 1]);

        $rows = (new WithTransaction($this->aqlExecutor))->run(
            static fn(AqlExecutorInterface $executor): array => $executor->executeAql($select)->finalize()->toArray()
        );

        $this->assertSame([['id' => $id]], $rows);
        $this->assertStringEndsWith(' ORDER BY `t0`.`id` LIMIT 1 FOR UPDATE', $this->mySql->lastSql);
    }

    /** A plain connection whose UPDATE of a locked row fails after one second instead of fifty. */
    private function secondConnection(): \PDO
    {
        $connection = MariaDb::pdo();
        $connection->exec('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }

    /** @return list<array<string, mixed>> */
    private function lockingSelect(AqlExecutorInterface $executor, int $id, string $lock): array
    {
        $select = (new AqlParser())->parse('SELECT id FROM AqlRoundTrip WHERE id = {id}' . $lock);
        $select->applyParameters(['id' => $id]);

        return $executor->executeAql($select)->finalize()->toArray();
    }

    public function testOnDuplicateKeyUpdateWithValuesKeepsOneRowWithTheSecondValues(): void
    {
        foreach ([[1, 'first'], [2, 'second']] as [$bonus, $label]) {
            $upsert = (new AqlParser())->parse(
                'INSERT INTO AqlRoundTripLease (ownerId, expiresAt, windowStart, attempts, bonus)'
                . ' VALUES ({owner}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), {attempts}, {bonus})'
                . ' ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), bonus = VALUES(bonus)'
            );
            $upsert->applyParameters(['owner' => 9, 'attempts' => $bonus * 10, 'bonus' => $bonus]);
            $this->aqlExecutor->executeAql($upsert);
        }

        $this->assertStringEndsWith(
            ' ON DUPLICATE KEY UPDATE `attempts` = VALUES(`attempts`), `bonus` = VALUES(`bonus`)',
            $this->mySql->lastSql
        );

        $rows = $this->pdo->query('SELECT ownerId, attempts, bonus FROM aql_round_trip_lease')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertSame([['ownerId' => 9, 'attempts' => 20, 'bonus' => 2]], $rows);
    }

    /** In the update list a column is the existing row, and VALUES(column) is the value the INSERT offered. */
    public function testOnDuplicateKeyUpdateReadsTheExistingRowAndTheOfferedValue(): void
    {
        $this->insertLeases([[4, '+1 hour', 100, '+1 hour', 7]]);

        $upsert = (new AqlParser())->parse(
            'INSERT INTO AqlRoundTripLease (ownerId, expiresAt, windowStart, attempts, bonus)'
            . ' VALUES ({owner}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), {attempts}, {bonus})'
            . ' ON DUPLICATE KEY UPDATE attempts = attempts + VALUES(attempts), bonus = bonus + {bonus}'
        );
        $upsert->applyParameters(['owner' => 4, 'attempts' => 5, 'bonus' => 1]);
        $this->aqlExecutor->executeAql($upsert);

        $row = $this->pdo->query('SELECT attempts, bonus FROM aql_round_trip_lease')->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(['attempts' => 12, 'bonus' => 101], $row);
    }

    /** The update list renders its columns bare, so an entity-qualified column runs the same as an unqualified one. */
    public function testOnDuplicateKeyUpdateAcceptsEntityQualifiedColumns(): void
    {
        $this->insertLeases([[1, '+1 hour', null, '+1 hour', 10]]);

        $upsert = (new AqlParser())->parse(
            'INSERT INTO AqlRoundTripLease (ownerId, expiresAt, windowStart, attempts)'
            . ' VALUES (1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 1) ON DUPLICATE KEY UPDATE'
            . ' AqlRoundTripLease.attempts = AqlRoundTripLease.attempts + VALUES(AqlRoundTripLease.attempts)'
        );
        $this->aqlExecutor->executeAql($upsert);

        $this->assertStringEndsWith(
            ' ON DUPLICATE KEY UPDATE `attempts` = `attempts` + VALUES(`attempts`)',
            $this->mySql->lastSql
        );
        $this->assertSame(11, (int) $this->pdo->query('SELECT attempts FROM aql_round_trip_lease')->fetchColumn());
    }

    /** The counter of a time window: 1, then 2 inside the window, then 1 again once the window is over. */
    public function testOnDuplicateKeyUpdateCountsInsideTheWindowAndRestartsAfterIt(): void
    {
        $upsert = static function (): object {
            $query = (new AqlParser())->parse(
                'INSERT INTO AqlRoundTripLease (ownerId, expiresAt, windowStart, attempts)'
                . ' VALUES ({owner}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 1) ON DUPLICATE KEY UPDATE'
                . ' attempts = IF(windowStart + INTERVAL {seconds} SECOND <= UTC_TIMESTAMP(6), 1, attempts + 1),'
                . ' windowStart = IF(windowStart + INTERVAL {seconds} SECOND <= UTC_TIMESTAMP(6),'
                . ' UTC_TIMESTAMP(6), windowStart)'
            );
            $query->applyParameters(['owner' => 3, 'seconds' => 60]);

            return $query;
        };
        $state = fn(): array => $this->pdo->query('SELECT attempts, windowStart FROM aql_round_trip_lease')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->aqlExecutor->executeAql($upsert());
        $first = $state();
        $this->aqlExecutor->executeAql($upsert());
        $second = $state();

        $this->assertCount(1, $second);
        $this->assertSame([1, 2], [$first[0]['attempts'], $second[0]['attempts']]);
        $this->assertSame($first[0]['windowStart'], $second[0]['windowStart']);

        $this->pdo->exec('UPDATE aql_round_trip_lease SET windowStart = UTC_TIMESTAMP(6) - INTERVAL 61 SECOND');
        $this->aqlExecutor->executeAql($upsert());
        $third = $state();

        $this->assertSame(1, $third[0]['attempts']);
        $this->assertEqualsWithDelta($this->serverUtcNow(), $this->microtime($third[0]['windowStart']), 5.0);
    }

    /** @return list<int> */
    private function ownerIds(array $rows): array
    {
        return \array_map('intval', \array_column($rows, 'ownerId'));
    }

    private function serverUtcNow(): float
    {
        return $this->microtime($this->pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn());
    }

    /** Seconds since the epoch of a UTC DATETIME(6) string as the server returns it. */
    private function microtime(string $datetime): float
    {
        return (float) (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->format('U.u');
    }

    /**
     * @param list<array{0: int, 1: string, 2?: int|null, 3?: string, 4?: int}> $leases owner id, expiry as a relative
     *        time string, bonus, window start as a relative time string (default: the expiry) and attempts
     */
    private function insertLeases(array $leases): void
    {
        $insert = $this->pdo->prepare('INSERT INTO aql_round_trip_lease'
            . ' (ownerId, expiresAt, windowStart, attempts, bonus) VALUES (?, ?, ?, ?, ?)');
        $format = static fn(string $relative): string => (new \DateTimeImmutable($relative, new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        foreach ($leases as $lease) {
            $insert->execute([
                $lease[0], $format($lease[1]), $format($lease[3] ?? $lease[1]), $lease[4] ?? 0, $lease[2] ?? null,
            ]);
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

    public function testInsertSelectCopiesTheSelectedRowsWithAParameterInTheSelectList(): void
    {
        $first = $this->insert($this->aqlExecutor, 1);
        $this->insert($this->aqlExecutor, 2);
        $third = $this->insert($this->aqlExecutor, 3);
        $query = (new AqlParser())->parse('INSERT INTO AqlRoundTripLink (roundTripId, label)'
            . ' SELECT id, {label} FROM AqlRoundTrip WHERE id <> {skip} ORDER BY id DESC');
        $query->applyParameters(['label' => 'copied', 'skip' => $first + 1]);

        $this->assertSame(2, $this->aqlExecutor->executeAql($query)->getAffectedRows());
        $this->assertSame([[$third, 'copied'], [$first, 'copied']],
            $this->pdo->query('SELECT roundTripId, label FROM aql_round_trip_link ORDER BY id')->fetchAll(\PDO::FETCH_NUM));
    }

    public function testInsertSelectWithinOneTableReturnsTheIdOfTheNewRow(): void
    {
        $this->insert($this->aqlExecutor, 1);
        $source = $this->insert($this->aqlExecutor, 2);
        $query = (new AqlParser())->parse('INSERT INTO AqlRoundTrip (value) SELECT value FROM AqlRoundTrip WHERE id = {id}');
        $query->applyParameters(['id' => $source]);
        $key = $this->aqlExecutor->executeAql($query)->getLastPrimaryKey();

        $this->assertIsArray($key);
        $this->assertSame($source + 1, (int) $key['id']);
        $this->assertSame([['id' => $source + 1, 'value' => 'worker-2']], $this->select($this->aqlExecutor, $source + 1));
    }

    public function testInsertSelectIntoACompositeKeyFiresTheTriggerForEveryRow(): void
    {
        $this->pdo->exec('CREATE TABLE aql_round_trip_label (roundTripId INT NOT NULL, label VARCHAR(64) NOT NULL,'
            . ' PRIMARY KEY (roundTripId, label)) ENGINE=InnoDB');
        // Refuses label "b" only for the row valued worker-3, so a copy there fails on its second row.
        $this->pdo->exec("CREATE TRIGGER aql_round_trip_label_bi BEFORE INSERT ON aql_round_trip_label FOR EACH ROW"
            . " BEGIN IF NEW.label = 'b' AND NEW.roundTripId = (SELECT id FROM aql_round_trip WHERE value = 'worker-3')"
            . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused label'; END IF; END");
        $from = $this->insert($this->aqlExecutor, 1);
        $to = $this->insert($this->aqlExecutor, 2);
        $refused = $this->insert($this->aqlExecutor, 3);
        $this->pdo->exec("INSERT INTO aql_round_trip_label VALUES ($from, 'a'), ($from, 'b')");
        $copy = 'INSERT INTO AqlRoundTripLabel (roundTripId, label)'
            . ' SELECT {to}, label FROM AqlRoundTripLabel WHERE roundTripId = {from} ORDER BY label';

        $query = (new AqlParser())->parse($copy);
        $query->applyParameters(['to' => $to, 'from' => $from]);
        $this->assertSame(2, $this->aqlExecutor->executeAql($query)->getAffectedRows());

        $query = (new AqlParser())->parse($copy);
        $query->applyParameters(['to' => $refused, 'from' => $from]);
        try {
            $this->aqlExecutor->executeAql($query);
            $this->fail('The trigger did not refuse the second row');
        } catch (\IfCastle\AQL\Storage\Exceptions\StorageException) {
        }

        $this->assertSame([[$from, 'a'], [$from, 'b'], [$to, 'a'], [$to, 'b']],
            $this->pdo->query('SELECT roundTripId, label FROM aql_round_trip_label ORDER BY roundTripId, label')
                ->fetchAll(\PDO::FETCH_NUM));
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
