<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;
use IfCastle\Exceptions\UnexpectedValue;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A query marked withPreparing() reaches MariaDB as a prepared statement: its SQL holds placeholders, and every
 * value, of a named parameter, of an unnamed one or of a literal, is bound to them.
 */
class AqlPreparedStatementMariaDbTest extends TestCaseWithDiContainer
{
    private \PDO $pdo;

    private SqlRecordingMySql $mySql;

    private AqlExecutorInterface $aqlExecutor;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->dropTables();
        $this->pdo->exec('CREATE TABLE aql_round_trip (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL,'
            . ' UNIQUE KEY uniq_value (value)) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_round_trip_event (id INT AUTO_INCREMENT PRIMARY KEY, ownerId INT NOT NULL,'
            . ' createdAt INT NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_binary_hash (id INT AUTO_INCREMENT PRIMARY KEY, hash BINARY(32) NOT NULL)'
            . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->mySql = new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 4] + $this->pdoOptions(),
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
        $this->dropTables();
    }

    public function testInsertBindsANamedParameter(): void
    {
        // A quote and a backslash would break the SQL if the value were pasted into it unescaped.
        $value = "it's \\ bound";
        $insert = $this->prepared('INSERT INTO AqlRoundTrip SET value = {value}', ['value' => $value]);

        $this->assertSame(1, $this->aqlExecutor->executeAql($insert)->getAffectedRows());

        $this->assertSame('INSERT INTO `aql_round_trip` SET `value` = :p0', $this->mySql->lastStatementSql);
        $this->assertSame([$value], $this->values());
    }

    public function testSelectBindsNamedUnnamedAndLiteralValues(): void
    {
        $this->insertValues('a', 'b', 'c', 'd');

        // {from} is named, 'd' is a literal, and where() adds an unnamed parameter (Parameter::fromValue).
        $select = $this->prepared(
            'SELECT value FROM AqlRoundTrip WHERE id >= {from} AND value != "d" ORDER BY id',
            ['from' => 2]
        );
        $select->where('value', 'c');

        $this->assertSame([['value' => 'c']], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
        $this->assertSame(3, \substr_count((string) $this->mySql->lastStatementSql, ':p'));
        $this->assertStringNotContainsString("'c'", (string) $this->mySql->lastStatementSql);
        $this->assertStringNotContainsString("'d'", (string) $this->mySql->lastStatementSql);
    }

    public function testTwoUnnamedParametersKeepTheirOwnValues(): void
    {
        $this->insertValues('a', 'b');

        $select = $this->prepared('SELECT value FROM AqlRoundTrip WHERE id > 0 ORDER BY id');
        $select->where('value', 'b');
        $select->where('id', $this->idOf('b'));

        $this->assertSame([['value' => 'b']], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
    }

    public function testTheSameQueryRunsAgainWithNewParameterValues(): void
    {
        $this->insertValues('a', 'b', 'c');

        $select = $this->prepared('SELECT value FROM AqlRoundTrip WHERE value = {value}');

        foreach (['a', 'c'] as $value) {
            $select->applyParameters(['value' => $value]);
            $this->assertSame([['value' => $value]], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function preparingModes(): iterable
    {
        yield 'prepared' => [true];
        yield 'plain' => [false];
    }

    #[DataProvider('preparingModes')]
    public function testAConditionAddedAfterARunFiltersTheNextRun(bool $preparing): void
    {
        $this->insertValues('a', 'b');

        $select = (new AqlParser())->parse('SELECT value FROM AqlRoundTrip WHERE value != {skip} ORDER BY id');
        $this->assertInstanceOf(QueryInterface::class, $select);

        if ($preparing) {
            $select->withPreparing();
        }

        $select->applyParameters(['skip' => 'none']);
        $this->assertSame(
            [['value' => 'a'], ['value' => 'b']],
            $this->aqlExecutor->executeAql($select)->finalize()->toArray()
        );

        $select->where('id', $this->idOf('b'));
        $this->assertSame([['value' => 'b']], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
    }

    public function testARunAgainTakesTheNewValuesWrittenIntoTheText(): void
    {
        $this->insertValues('a', 'b', 'c', 'd');

        $select = $this->prepared(
            'SELECT value FROM AqlRoundTrip WHERE value IN ({values}) ORDER BY id LIMIT {n}',
            ['values' => ['a', 'b'], 'n' => 1]
        );
        $this->assertSame([['value' => 'a']], $this->aqlExecutor->executeAql($select)->finalize()->toArray());

        $select->applyParameters(['values' => ['b', 'c', 'd'], 'n' => 2]);
        $this->assertSame(
            [['value' => 'b'], ['value' => 'c']],
            $this->aqlExecutor->executeAql($select)->finalize()->toArray()
        );
    }

    public function testARunAfterAFailedOneBindsOnlyItsOwnPlaceholders(): void
    {
        $this->insertEvents([[1, 10], [1, 20], [2, 30]]);

        $select = $this->prepared(
            'SELECT createdAt FROM AqlRoundTripEvent WHERE ownerId = {owner} ORDER BY createdAt LIMIT {n}',
            ['owner' => 1, 'n' => '1']
        );

        try {
            $this->aqlExecutor->executeAql($select);
            $this->fail('A string LIMIT value was accepted');
        } catch (UnexpectedValue) {
        }

        $select->applyParameters(['n' => 1]);
        $this->assertSame([10], $this->createdAt($this->aqlExecutor->executeAql($select)->finalize()->toArray()));
    }

    public function testARepeatedNamedParameterIsBoundAtEachPlace(): void
    {
        $this->insertValues('same', 'other');

        $select = $this->prepared(
            'SELECT value FROM AqlRoundTrip WHERE (value = {value} OR value = CONCAT({value}, "")) ORDER BY id',
            ['value' => 'same']
        );

        $this->assertSame([['value' => 'same']], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
        // Each {value} and the "" literal.
        $this->assertSame(3, \substr_count((string) $this->mySql->lastStatementSql, ':p'));
    }

    public function testNullAndIntegerValuesAreBoundWithTheirTypes(): void
    {
        $this->insertEvents([[1, 10], [1, 20], [1, 30], [2, 40]]);

        // The owner is bound as an integer; LIMIT writes its count into the SQL itself.
        $select = $this->prepared(
            'SELECT createdAt FROM AqlRoundTripEvent WHERE ownerId = {owner} ORDER BY createdAt LIMIT {n}',
            ['owner' => 1, 'n' => 2]
        );

        $this->assertSame([10, 20], $this->createdAt($this->aqlExecutor->executeAql($select)->finalize()->toArray()));

        $nothing = $this->prepared('SELECT createdAt FROM AqlRoundTripEvent WHERE ownerId = {owner}', ['owner' => null]);
        $this->assertSame([], $this->aqlExecutor->executeAql($nothing)->finalize()->toArray());
    }

    public function testDeleteWithALimitParameterAndNoOffsetBindsOnlyWhatItRenders(): void
    {
        $this->insertEvents([[1, 30], [1, 10], [1, 20], [2, 5]]);

        $delete = $this->prepared(
            'DELETE FROM AqlRoundTripEvent WHERE ownerId = {owner} ORDER BY createdAt LIMIT {n}',
            ['owner' => 1, 'n' => 2]
        );

        $this->assertSame(2, $this->aqlExecutor->executeAql($delete)->affected());
        $this->assertSame(
            'DELETE FROM `aql_round_trip_event` WHERE `aql_round_trip_event`.`ownerId` = :p0'
            . ' ORDER BY `aql_round_trip_event`.`createdAt` LIMIT 2',
            $this->mySql->lastStatementSql
        );
        $this->assertSame(
            [[1, 30], [2, 5]],
            \array_map(
                static fn(array $row): array => \array_map('intval', $row),
                $this->pdo->query('SELECT ownerId, createdAt FROM aql_round_trip_event ORDER BY id')
                    ->fetchAll(\PDO::FETCH_NUM)
            )
        );
    }

    public function testABooleanIsBoundAsOneOrZero(): void
    {
        $this->insertEvents([[1, 10], [0, 20]]);

        foreach ([[true, [10]], [false, [20]]] as [$flag, $expected]) {
            $select = $this->prepared('SELECT createdAt FROM AqlRoundTripEvent WHERE ownerId = {flag}', ['flag' => $flag]);
            $this->assertSame($expected, $this->createdAt($this->aqlExecutor->executeAql($select)->finalize()->toArray()));
        }
    }

    public function testBinaryValueRoundTripsByteExact(): void
    {
        // 0xFF never occurs in UTF-8; a NUL byte, a quote and a backslash must survive binding.
        $hash = "\xff\xfe\x00\x80'\\" . \str_repeat("\xc3", 26);
        $this->assertSame(32, \strlen($hash));

        $insert = $this->prepared('INSERT INTO AqlBinaryHash SET hash = {hash}', ['hash' => $hash]);
        $this->assertSame(1, $this->aqlExecutor->executeAql($insert)->getAffectedRows());

        $this->assertSame(
            ['FFFE0080275C' . \str_repeat('C3', 26)],
            $this->pdo->query('SELECT HEX(hash) FROM aql_binary_hash')->fetchAll(\PDO::FETCH_COLUMN)
        );

        $select = $this->prepared('SELECT hash FROM AqlBinaryHash WHERE hash = {hash}', ['hash' => $hash]);
        $this->assertSame([['hash' => $hash]], $this->aqlExecutor->executeAql($select)->finalize()->toArray());
        $this->assertStringNotContainsString("X'", (string) $this->mySql->lastStatementSql);
    }

    public function testAValueListStaysInTheStatementTextNextToBoundValues(): void
    {
        $this->insertValues('a', 'b', 'c');

        $select = $this->prepared(
            'SELECT value FROM AqlRoundTrip WHERE value IN ({values}) AND id > {from} ORDER BY id',
            ['values' => ['a', 'c'], 'from' => 0]
        );

        $this->assertSame(
            [['value' => 'a'], ['value' => 'c']],
            $this->aqlExecutor->executeAql($select)->finalize()->toArray()
        );
        $this->assertStringContainsString("('a','c')", (string) $this->mySql->lastStatementSql);
        $this->assertStringContainsString('> :p', (string) $this->mySql->lastStatementSql);
    }

    public function testAFailedExecutionRaisesTheStorageException(): void
    {
        $this->insertValues('taken');

        $insert = $this->prepared('INSERT INTO AqlRoundTrip SET value = {value}', ['value' => 'taken']);

        try {
            $this->aqlExecutor->executeAql($insert);
            $this->fail('A duplicate value was inserted through a prepared statement');
        } catch (DuplicateKeysException) {
            $this->assertSame(['taken'], $this->values());
        }
    }

    /**
     * PDO options of the storage under test besides the pool: none here, so pdo_mysql emulates prepares.
     *
     * @return array<int, mixed>
     */
    protected function pdoOptions(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function prepared(string $aql, array $parameters = []): QueryInterface
    {
        $query = (new AqlParser())->parse($aql);
        $this->assertInstanceOf(QueryInterface::class, $query);
        $query->withPreparing();
        $query->applyParameters($parameters);

        return $query;
    }

    private function insertValues(string ...$values): void
    {
        $insert = $this->pdo->prepare('INSERT INTO aql_round_trip (value) VALUES (?)');

        foreach ($values as $value) {
            $insert->execute([$value]);
        }
    }

    /**
     * @param list<array{int, int}> $events pairs of ownerId and createdAt
     */
    private function insertEvents(array $events): void
    {
        $insert = $this->pdo->prepare('INSERT INTO aql_round_trip_event (ownerId, createdAt) VALUES (?, ?)');

        foreach ($events as $event) {
            $insert->execute($event);
        }
    }

    private function idOf(string $value): int
    {
        $select = $this->pdo->prepare('SELECT id FROM aql_round_trip WHERE value = ?');
        $select->execute([$value]);

        return (int) $select->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function values(): array
    {
        return $this->pdo->query('SELECT value FROM aql_round_trip ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function createdAt(array $rows): array
    {
        return \array_map(static fn(array $row): int => (int) $row['createdAt'], $rows);
    }

    private function dropTables(): void
    {
        foreach (['aql_round_trip', 'aql_round_trip_event', 'aql_binary_hash'] as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
