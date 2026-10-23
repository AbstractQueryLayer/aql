<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\Count;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Entities\AqlRoundTripNote;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQuery;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A query over AqlRoundTripNote, kept in another storage, runs as a plan of several statements. Each query here is
 * also run on AqlRoundTripLocalNote, the same notes in the main storage, where it is one statement: both must give
 * the same rows.
 */
class AqlCrossStoragePlanMariaDbTest extends TestCaseWithDiContainer
{
    /** Note 5 is an orphan: no round trip 9 exists. */
    private const string NOTES      = '(1, 1, "alpha"), (2, 2, "beta"), (3, 2, "gamma"), (4, 3, "alpha"), (5, 9, "delta")';

    /** Round trip 4 has no notes. */
    private const string ROUND_TRIPS = '(1, "first"), (2, "second"), (3, "third"), (4, "fourth")';

    private \PDO $pdo;

    private SqlRecordingMySql $main;

    private SqlRecordingMySql $remote;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->seed();

        $this->main = $this->newStorage(StorageCollectionInterface::STORAGE_MAIN);
        $this->remote = $this->newStorage(AqlRoundTripNote::STORAGE);

        parent::setUp();
        $this->main->resolveDependencies($this->getDiContainer());
        $this->remote->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
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

    /**
     * Rows worked out by hand from the tables of seed().
     *
     * @return iterable<string, array{string, list<array<string, mixed>>}>
     */
    public static function selects(): iterable
    {
        yield 'join from the notes' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY id',
            [['label' => 'alpha'], ['label' => 'beta'], ['label' => 'gamma'], ['label' => 'alpha']],
        ];
        yield 'join from the notes filtered on the round trip' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip WHERE AqlRoundTrip.value = "second" ORDER BY id',
            [['label' => 'beta'], ['label' => 'gamma']],
        ];
        yield 'filter on the round trip without a join' => [
            'SELECT label FROM AqlRoundTripNote WHERE AqlRoundTrip.value <> "second" ORDER BY id',
            [['label' => 'alpha'], ['label' => 'alpha']],
        ];
        yield 'filter on the round trip that matches nothing' => [
            'SELECT label FROM AqlRoundTripNote WHERE AqlRoundTrip.value = "none" ORDER BY id',
            [],
        ];
        yield 'join to the notes filtered on a note' => [
            'SELECT value FROM AqlRoundTrip INNER JOIN AqlRoundTripNote WHERE AqlRoundTripNote.label = "alpha" ORDER BY id',
            [['value' => 'first'], ['value' => 'third']],
        ];
    }

    /**
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('selects')]
    public function testASelectAcrossStoragesGivesTheRowsOfOneStorage(string $aql, array $expected): void
    {
        $this->assertSame($expected, $this->rows(self::local($aql)), 'The single-storage reference differs');
        $this->assertSame($expected, $this->rows($aql));
    }

    /**
     * A round trip with two notes that pass the filter: one statement repeats it for each note, a plan across storages
     * keeps it once, as a semi-join does.
     */
    public function testAFilterOnSeveralRelatedRowsInAnotherStorageKeepsARowOnce(): void
    {
        $aql = 'SELECT value FROM AqlRoundTrip WHERE AqlRoundTripNote.label <> "alpha" ORDER BY id';

        $this->assertSame([['value' => 'second'], ['value' => 'second']], $this->rows(self::local($aql)));
        $this->assertSame([['value' => 'second']], $this->rows($aql));
    }

    /**
     * Joins across storages: the rows of the other storage are composed with the main ones as one statement joins them,
     * with their columns, a row repeated for each related row, and a row without one dropped by INNER or kept by LEFT.
     * Rows worked out by hand from the tables of seed().
     *
     * @return iterable<string, array{string, list<array<string, mixed>>}>
     */
    public static function joins(): iterable
    {
        yield 'a column of the joined round trip' => [
            'SELECT label, AqlRoundTrip.value FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY id',
            [
                ['label' => 'alpha', 'value' => 'first'], ['label' => 'beta', 'value' => 'second'],
                ['label' => 'gamma', 'value' => 'second'], ['label' => 'alpha', 'value' => 'third'],
            ],
        ];
        yield 'a column of a round trip joined by LEFT keeps the orphan' => [
            'SELECT label, AqlRoundTrip.value FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip ORDER BY id',
            [
                ['label' => 'alpha', 'value' => 'first'], ['label' => 'beta', 'value' => 'second'],
                ['label' => 'gamma', 'value' => 'second'], ['label' => 'alpha', 'value' => 'third'],
                ['label' => 'delta', 'value' => null],
            ],
        ];
        yield 'a column of the round trip without a JOIN' => [
            'SELECT label, AqlRoundTrip.value AS v FROM AqlRoundTripNote ORDER BY id',
            [
                ['label' => 'alpha', 'v' => 'first'], ['label' => 'beta', 'v' => 'second'],
                ['label' => 'gamma', 'v' => 'second'], ['label' => 'alpha', 'v' => 'third'],
            ],
        ];
        yield 'an expression over the round trip' => [
            'SELECT label, CONCAT(AqlRoundTrip.value, "!") AS v FROM AqlRoundTripNote INNER JOIN AqlRoundTrip WHERE id < 3 ORDER BY id',
            [['label' => 'alpha', 'v' => 'first!'], ['label' => 'beta', 'v' => 'second!']],
        ];
        yield 'an expression over the round trip without a JOIN' => [
            'SELECT label, CONCAT(AqlRoundTrip.value, "!") AS v FROM AqlRoundTripNote ORDER BY id',
            [
                ['label' => 'alpha', 'v' => 'first!'], ['label' => 'beta', 'v' => 'second!'],
                ['label' => 'gamma', 'v' => 'second!'], ['label' => 'alpha', 'v' => 'third!'],
            ],
        ];
        yield 'orphans by LEFT JOIN and IS NULL' => [
            'SELECT label FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE AqlRoundTrip.id IS NULL ORDER BY id',
            [['label' => 'delta']],
        ];
        yield 'IS NOT NULL under LEFT JOIN' => [
            'SELECT label FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE AqlRoundTrip.id IS NOT NULL AND label = "alpha" ORDER BY id',
            [['label' => 'alpha'], ['label' => 'alpha']],
        ];
        yield 'IS NULL under INNER JOIN' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip WHERE AqlRoundTrip.id IS NULL ORDER BY id',
            [],
        ];
        yield 'a round trip repeated for each note' => [
            'SELECT value FROM AqlRoundTrip INNER JOIN AqlRoundTripNote ORDER BY id',
            [['value' => 'first'], ['value' => 'second'], ['value' => 'second'], ['value' => 'third']],
        ];
        yield 'a round trip without notes kept by LEFT' => [
            'SELECT value FROM AqlRoundTrip LEFT JOIN AqlRoundTripNote ORDER BY id',
            [['value' => 'first'], ['value' => 'second'], ['value' => 'second'], ['value' => 'third'], ['value' => 'fourth']],
        ];
        yield 'round trips without notes' => [
            'SELECT value FROM AqlRoundTrip LEFT JOIN AqlRoundTripNote WHERE AqlRoundTripNote.id IS NULL ORDER BY id',
            [['value' => 'fourth']],
        ];
        yield 'a round trip repeated for each note that passes the filter' => [
            'SELECT value FROM AqlRoundTrip INNER JOIN AqlRoundTripNote WHERE AqlRoundTripNote.label <> "alpha" ORDER BY id',
            [['value' => 'second'], ['value' => 'second']],
        ];
        yield 'a LEFT JOIN with a filter on the round trip' => [
            'SELECT label, AqlRoundTrip.value FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE AqlRoundTrip.value = "second" ORDER BY id',
            [['label' => 'beta', 'value' => 'second'], ['label' => 'gamma', 'value' => 'second']],
        ];
        yield 'a LIMIT over a LEFT JOIN with IS NOT NULL, which runs in the query of the round trips' => [
            'SELECT label FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE AqlRoundTrip.id IS NOT NULL ORDER BY id DESC LIMIT 2',
            [['label' => 'alpha'], ['label' => 'gamma']],
        ];
        yield 'a LIMIT over a LEFT JOIN to one round trip' => [
            'SELECT label, AqlRoundTrip.value FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip ORDER BY id DESC LIMIT 2',
            [['label' => 'delta', 'value' => null], ['label' => 'alpha', 'value' => 'third']],
        ];
    }

    /**
     * The rows of each note of a round trip come in no order of their own, in one statement as across storages, so
     * they are compared sorted.
     *
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('joins')]
    public function testAJoinAcrossStoragesGivesTheRowsOfOneStatement(string $aql, array $expected): void
    {
        $this->assertSame(self::sorted($expected), self::sorted($this->rows(self::local($aql))), 'The single-storage reference differs');
        $this->assertSame(self::sorted($expected), self::sorted($this->rows($aql)));
    }

    /**
     * A note row repeated for each note of its round trip, with the label of each.
     */
    public function testAColumnOfTheToManySideRepeatsTheRow(): void
    {
        $aql = 'SELECT value, AqlRoundTripNote.label FROM AqlRoundTrip INNER JOIN AqlRoundTripNote ORDER BY id';
        $expected = [
            ['value' => 'first', 'label' => 'alpha'], ['value' => 'second', 'label' => 'beta'],
            ['value' => 'second', 'label' => 'gamma'], ['value' => 'third', 'label' => 'alpha'],
        ];

        $this->assertSame($expected, self::sorted($this->rows(self::local($aql))), 'The single-storage reference differs');
        $this->assertSame($expected, self::sorted($this->rows($aql)));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function sorted(array $rows): array
    {
        \usort($rows, static fn(array $a, array $b): int => \json_encode($a) <=> \json_encode($b));

        return $rows;
    }

    /**
     * Rows left by each write, worked out by hand from the tables of seed(): the table, then its rows as
     * "id:roundTripId:label" for notes and "id:value" for round trips.
     *
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function writes(): iterable
    {
        yield 'update notes filtered on the round trip' => [
            'UPDATE AqlRoundTripNote SET label = "changed" WHERE AqlRoundTrip.value = "second"',
            'aql_round_trip_note',
            ['1:1:alpha', '2:2:changed', '3:2:changed', '4:3:alpha', '5:9:delta'],
        ];
        yield 'delete notes filtered on the round trip' => [
            'DELETE FROM AqlRoundTripNote WHERE AqlRoundTrip.value <> "second"',
            'aql_round_trip_note',
            ['2:2:beta', '3:2:gamma', '5:9:delta'],
        ];
        yield 'update round trips filtered on a note' => [
            'UPDATE AqlRoundTrip SET value = "changed" WHERE AqlRoundTripNote.label = "alpha"',
            'aql_round_trip',
            ['1:changed', '2:second', '3:changed', '4:fourth'],
        ];
        yield 'delete round trips filtered on a note' => [
            'DELETE FROM AqlRoundTrip WHERE AqlRoundTripNote.label = "gamma"',
            'aql_round_trip',
            ['1:first', '3:third', '4:fourth'],
        ];
        yield 'delete with an alias, ORDER BY and LIMIT in one storage' => [
            'DELETE FROM AqlRoundTripNote n WHERE n.label = "alpha" ORDER BY n.id DESC LIMIT 1',
            'aql_round_trip_note',
            ['1:1:alpha', '2:2:beta', '3:2:gamma', '5:9:delta'],
        ];
        yield 'update notes with a filter that matches nothing' => [
            'UPDATE AqlRoundTripNote SET label = "changed" WHERE AqlRoundTrip.value = "none"',
            'aql_round_trip_note',
            ['1:1:alpha', '2:2:beta', '3:2:gamma', '4:3:alpha', '5:9:delta'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('writes')]
    public function testAWriteAcrossStoragesChangesTheRowsOfOneStorage(string $aql, string $table, array $expected): void
    {
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse(self::local($aql)));
        $this->assertSame($expected, $this->tableRows(self::local($table)), 'The single-storage reference differs');

        $this->seed();
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse($aql));
        $this->assertSame($expected, $this->tableRows($table));
    }

    /**
     * Across storages the DELETE joins nothing in its own statement, so it keeps the single-table form that takes
     * ORDER BY and LIMIT; one storage cannot run this query, a multi-table DELETE takes neither.
     */
    public function testADeleteFilteredInAnotherStorageTakesALimit(): void
    {
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse(
            'DELETE FROM AqlRoundTripNote WHERE AqlRoundTrip.value = "second" ORDER BY id LIMIT 1'
        ));

        $this->assertSame(['1:1:alpha', '3:2:gamma', '4:3:alpha', '5:9:delta'], $this->tableRows('aql_round_trip_note'));
    }

    /**
     * Queries whose result a plan across storages cannot give: one storage would return or change other rows.
     *
     * @return iterable<string, array{string}>
     */
    public static function refusedQueries(): iterable
    {
        yield 'assigning a column of the other storage' => ['UPDATE AqlRoundTripNote SET AqlRoundTrip.value = "x" WHERE id = 1'];
        yield 'reading a column of the other storage in SET' => ['UPDATE AqlRoundTripNote SET label = AqlRoundTrip.value WHERE id = 1'];
        yield 'IS NULL, true for a note without its round trip' => ['SELECT label FROM AqlRoundTripNote WHERE AqlRoundTrip.id IS NULL'];
        yield 'a function, which may be true for a missing round trip' => [
            'SELECT label FROM AqlRoundTripNote WHERE COALESCE(AqlRoundTrip.value, "none") = "none"',
        ];
        yield 'a condition under OR' => ['SELECT label FROM AqlRoundTripNote WHERE (AqlRoundTrip.value = "first" OR label = "beta")'];
        yield 'a DELETE of orphans' => ['DELETE FROM AqlRoundTripNote WHERE AqlRoundTrip.id IS NULL'];
        yield 'an aggregate over a join, which would count orphans' => ['SELECT COUNT(id) AS c FROM AqlRoundTripNote INNER JOIN AqlRoundTrip'];
        yield 'every column of a join' => ['SELECT * FROM AqlRoundTripNote INNER JOIN AqlRoundTrip'];
        yield 'an expression over columns of both storages' => [
            'SELECT CONCAT(AqlRoundTrip.value, ":", id) AS v FROM AqlRoundTripNote INNER JOIN AqlRoundTrip',
        ];
        yield 'a condition over columns of both storages' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip WHERE AqlRoundTrip.id = id',
        ];
        yield 'a LIMIT over an INNER JOIN, which drops orphans after it' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY id LIMIT 2',
        ];
        yield 'a LIMIT over a join that repeats rows' => ['SELECT value FROM AqlRoundTrip LEFT JOIN AqlRoundTripNote ORDER BY id LIMIT 2'];
        yield 'a LIMIT over a join filtered after it' => [
            'SELECT label FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE AqlRoundTrip.id IS NULL ORDER BY id LIMIT 1',
        ];
        yield 'ORDER BY the other storage' => ['SELECT label, AqlRoundTrip.value AS v FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY v'];
        yield 'ORDER BY a column of the other storage' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY AqlRoundTrip.value',
        ];
        yield 'ORDER BY an expression over the other storage' => [
            'SELECT label, CONCAT(AqlRoundTrip.value, "!") AS v FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY v',
        ];
        yield 'an aggregate over the other storage' => [
            'SELECT label, MAX(AqlRoundTrip.value) AS m FROM AqlRoundTripNote INNER JOIN AqlRoundTrip',
        ];
        yield 'a UNION with a join' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip UNION SELECT label FROM AqlRoundTripLocalNote',
        ];
        yield 'GROUP BY over a join' => ['SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip GROUP BY label'];
        yield 'IS NULL under OR in a LEFT JOIN' => [
            'SELECT label FROM AqlRoundTripNote LEFT JOIN AqlRoundTrip WHERE (AqlRoundTrip.id IS NULL OR label = "beta")',
        ];
        yield 'a join under a join to the other storage' => [
            'SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip { INNER JOIN AqlRoundTripLocalNote }',
        ];
    }

    #[DataProvider('refusedQueries')]
    public function testAQueryAcrossStoragesThatWouldChangeItsResultIsRefused(string $aql): void
    {
        try {
            $this->getAqlExecutor()->executeAql((new AqlParser())->parse($aql));
            $this->fail('The query ran: ' . $aql);
        } catch (QueryException) {
        }

        $this->assertSame(['1:1:alpha', '2:2:beta', '3:2:gamma', '4:3:alpha', '5:9:delta'], $this->tableRows('aql_round_trip_note'));
    }

    /**
     * A COUNT counts the rows of its main query: it holds where the join keeps each of them once, and is refused where
     * the join would drop or repeat them.
     *
     * @return iterable<string, array{string, string, string|int}> join type, WHERE, the count or the refusal
     */
    public static function counts(): iterable
    {
        yield 'INNER restricted by a condition on the round trip' => [JoinInterface::INNER, 'AqlRoundTrip.value = "second"', 2];
        yield 'LEFT without a condition' => [JoinInterface::LEFT, '', 5];
        yield 'INNER, which would drop the orphan after counting' => [JoinInterface::INNER, '', 'a COUNT counts the rows'];
        yield 'LEFT with IS NULL, filtered after counting' => [JoinInterface::LEFT, 'AqlRoundTrip.id IS NULL', 'a COUNT counts the rows'];
    }

    #[DataProvider('counts')]
    public function testACountOverAJoinAcrossStorages(string $joinType, string $where, string|int $expected): void
    {
        $count = static function (string $note) use ($joinType, $where): Count {
            $count = new Count($note);
            $count->getFrom()->addJoin(new Join($joinType, 'AqlRoundTrip'));

            if ($where !== '') {
                $count->getWhere()->add((new AqlParser())->parse('SELECT id FROM T WHERE ' . $where)->getWhere()->getChildNodes()[0]);
            }

            return $count;
        };

        if (\is_string($expected)) {
            $this->expectException(QueryException::class);
            $this->expectExceptionMessage($expected);
        } else {
            $this->assertSame($expected, $this->countRows($count('AqlRoundTripLocalNote')), 'The single-storage reference differs');
        }

        $this->assertSame($expected, $this->countRows($count('AqlRoundTripNote')));
    }

    /**
     * A join of a type a plan across storages cannot compose is refused, not read as another type.
     */
    public function testARightJoinAcrossStoragesIsRefused(): void
    {
        $select = (new AqlParser())->parse('SELECT label FROM AqlRoundTripNote');
        $select->getFrom()->addJoin(new Join(JoinInterface::RIGHT, 'AqlRoundTrip'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('cannot be composed row by row');

        $this->getAqlExecutor()->executeAql($select);
    }

    private function countRows(Count $count): int
    {
        return $this->getAqlExecutor()->executeAql($count)->getFirstColumnAsInt();
    }

    /**
     * The plan of a preprocessed query is built once and run by each call: the query on the other storage runs in the
     * transaction of the call and reads the keys of this run only. The second run sees, inside its transaction, a
     * round trip changed there and not committed.
     */
    public function testAPreprocessedQueryAcrossStoragesRunsInTheTransactionOfEachCall(): void
    {
        $query = $this->preprocessed('SELECT label FROM AqlRoundTripNote WHERE AqlRoundTrip.value = "second" ORDER BY id');

        $first = (new WithTransaction($this->getAqlExecutor()))->run(
            static fn(AqlExecutorInterface $executor): array => $executor->executeAql($query)->finalize()->toArray()
        );

        $second = $this->runAndRollBack(function (AqlExecutorInterface $executor) use ($query): array {
            $executor->executeAql((new AqlParser())->parse('UPDATE AqlRoundTrip SET value = "second" WHERE id = 3'));

            return $executor->executeAql($query)->finalize()->toArray();
        });

        $this->assertSame([['label' => 'beta'], ['label' => 'gamma']], $first);
        $this->assertSame([['label' => 'beta'], ['label' => 'gamma'], ['label' => 'alpha']], $second);
        $this->assertSame(1, \substr_count((string) $this->remote->lastSql, ' IN '), (string) $this->remote->lastSql);
        $this->assertSame(['1:first', '2:second', '3:third', '4:fourth'], $this->tableRows('aql_round_trip'));
    }

    /**
     * Without a filter the notes run first and the round trips are read by the keys of the notes: a second run reads
     * them by the keys of its own run.
     */
    public function testAPreprocessedJoinAcrossStoragesRunsTwice(): void
    {
        $query = $this->preprocessed('SELECT label FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY id');

        $first = $this->getAqlExecutor()->executeAql($query)->finalize()->toArray();
        $this->pdo->exec('DELETE FROM aql_round_trip_note WHERE id > 1');
        $second = $this->getAqlExecutor()->executeAql($query)->finalize()->toArray();

        $this->assertCount(4, $first);
        $this->assertSame([['label' => 'alpha']], $second);
        $this->assertStringContainsString('IN (1)', (string) $this->main->lastSql);
    }

    /**
     * A join with a column of the other storage composes the rows of each run only.
     */
    public function testAPreprocessedJoinWithAColumnAcrossStoragesRunsTwice(): void
    {
        $query = $this->preprocessed('SELECT label, AqlRoundTrip.value FROM AqlRoundTripNote INNER JOIN AqlRoundTrip ORDER BY id');

        $first = $this->getAqlExecutor()->executeAql($query)->finalize()->toArray();
        $this->pdo->exec('UPDATE aql_round_trip SET value = "changed" WHERE id = 1');
        $this->pdo->exec('DELETE FROM aql_round_trip_note WHERE id IN (2, 3)');
        $second = $this->getAqlExecutor()->executeAql($query)->finalize()->toArray();

        $this->assertCount(4, $first);
        $this->assertSame([['label' => 'alpha', 'value' => 'changed'], ['label' => 'alpha', 'value' => 'third']], $second);
    }

    /**
     * Each run reads the round trips that match when it runs, in its own transaction.
     */
    public function testAPreprocessedUpdateAcrossStoragesRunsInTwoTransactions(): void
    {
        $query = $this->preprocessed('UPDATE AqlRoundTripNote SET label = CONCAT(label, "+") WHERE AqlRoundTrip.value = "first"');

        $this->runInTransaction($query);
        $this->pdo->exec('UPDATE aql_round_trip SET value = "first" WHERE id = 3');
        $this->runInTransaction($query);

        $this->assertSame(['1:1:alpha++', '2:2:beta', '3:2:gamma', '4:3:alpha+', '5:9:delta'], $this->tableRows('aql_round_trip_note'));
    }

    public function testAPreprocessedDeleteAcrossStoragesRunsInTwoTransactions(): void
    {
        $query = $this->preprocessed('DELETE FROM AqlRoundTrip WHERE AqlRoundTripNote.label = "alpha"');

        $this->runInTransaction($query);
        $this->assertSame(['2:second', '4:fourth'], $this->tableRows('aql_round_trip'));

        $this->pdo->exec('INSERT INTO aql_round_trip (id, value) VALUES (1, "first")');
        $this->runInTransaction($query);
        $this->assertSame(['2:second', '4:fourth'], $this->tableRows('aql_round_trip'));
    }

    private function runInTransaction(PreprocessedQuery $query): void
    {
        (new WithTransaction($this->getAqlExecutor()))->run(
            static fn(AqlExecutorInterface $executor) => $executor->executeAql($query)
        );
    }

    /**
     * Runs $work in a transaction that is rolled back, and returns what it returned.
     */
    private function runAndRollBack(callable $work): mixed
    {
        $result = null;

        try {
            (new WithTransaction($this->getAqlExecutor()))->run(static function (AqlExecutorInterface $executor) use ($work, &$result): never {
                $result = $work($executor);

                throw new \DomainException('roll back');
            });
        } catch (\DomainException) {
        }

        return $result;
    }

    private function preprocessed(string $aql): PreprocessedQuery
    {
        return new PreprocessedQuery(static fn() => (new AqlParser())->parse($aql), \md5($aql));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $aql): array
    {
        return $this->getAqlExecutor()->executeAql((new AqlParser())->parse($aql))->finalize()->toArray();
    }

    /**
     * The same query on the notes of the main storage.
     */
    private static function local(string $aql): string
    {
        return \str_replace(['AqlRoundTripNote', 'aql_round_trip_note'], ['AqlRoundTripLocalNote', 'aql_round_trip_local_note'], $aql);
    }

    /**
     * @return list<string>
     */
    private function tableRows(string $table): array
    {
        return $this->pdo->query('SELECT CONCAT_WS(":", ' . ($table === 'aql_round_trip' ? 'id, value' : 'id, roundTripId, label')
            . ') FROM ' . $table . ' ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Creates the tables with three round trips and four notes on them, kept twice: in the other storage and in the
     * main one.
     */
    private function seed(): void
    {
        $this->dropTables();
        $this->pdo->exec('CREATE TABLE aql_round_trip (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL)'
            . ' ENGINE=InnoDB');
        $this->pdo->exec('INSERT INTO aql_round_trip (id, value) VALUES ' . self::ROUND_TRIPS);

        foreach (['aql_round_trip_note', 'aql_round_trip_local_note'] as $table) {
            $this->pdo->exec('CREATE TABLE ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, roundTripId INT NOT NULL,'
                . ' label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
            $this->pdo->exec('INSERT INTO ' . $table . ' (id, roundTripId, label) VALUES ' . self::NOTES);
        }
    }

    private function newStorage(string $name): SqlRecordingMySql
    {
        $storage = new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 2],
        ]);
        $storage->setStorageName($name);

        return $storage;
    }

    private function dropTables(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_local_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
    }
}
