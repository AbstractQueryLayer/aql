<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\QueryOption;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\DTO\Exceptions\MappingException;
use IfCastle\AQL\DTO\RoundTripDto;
use IfCastle\AQL\DTO\RoundTripLocalNoteForeignKeyDto;
use IfCastle\AQL\DTO\RoundTripSlotDto;
use IfCastle\AQL\DTO\RoundTripSlotNullableKeyDto;
use IfCastle\AQL\DTO\RoundTripSlotPartialKeyDto;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;

/**
 * DTO writes on a two-column primary key, (roundTripId, position), on MariaDB. The table holds two rows sharing
 * roundTripId 1, and the row a test names comes second in key order: a filter on roundTripId alone with LIMIT 1
 * would change the first one. AqlRoundTrip covers a single-column key without a typical `@id`.
 */
class AqlDtoCompositeKeyMariaDbTest extends TestCaseWithDiContainer
{
    private \PDO $pdo;

    private MySql $mySql;

    private AqlExecutorInterface $aqlExecutor;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_local_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_slot');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
        $this->pdo->exec('CREATE TABLE aql_round_trip (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL)'
            . ' ENGINE=InnoDB');
        $this->pdo->exec("INSERT INTO aql_round_trip (value) VALUES ('one'), ('two')");
        $this->pdo->exec('CREATE TABLE aql_round_trip_slot (roundTripId INT NOT NULL, position INT NOT NULL,'
            . ' label VARCHAR(64) NOT NULL, PRIMARY KEY (roundTripId, position)) ENGINE=InnoDB');
        $this->pdo->exec("INSERT INTO aql_round_trip_slot VALUES (1, 0, 'first'), (1, 1, 'second'), (2, 1, 'other')");
        $this->pdo->exec('CREATE TABLE aql_round_trip_local_note (id INT AUTO_INCREMENT PRIMARY KEY,'
            . ' roundTripId INT NOT NULL, label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec("INSERT INTO aql_round_trip_local_note (roundTripId, label) VALUES (1, 'a'), (1, 'b')");
        $this->mySql = new MySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
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
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_local_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_slot');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
    }

    public function testUpdateChangesOnlyTheRowNamedByEveryKeyColumn(): void
    {
        (new RoundTripSlotDto(1, 1, 'renamed'))->update($this->aqlExecutor);

        $this->assertSame([[1, 0, 'first'], [1, 1, 'renamed'], [2, 1, 'other']], $this->rows());
    }

    public function testDeleteRemovesOnlyTheRowNamedByEveryKeyColumn(): void
    {
        (new RoundTripSlotDto(1, 1, 'second'))->delete($this->aqlExecutor);

        $this->assertSame([[1, 0, 'first'], [2, 1, 'other']], $this->rows());
    }

    public function testInsertKeepsAKeyColumnHoldingZero(): void
    {
        (new RoundTripSlotDto(3, 0, 'zero'))->insert($this->aqlExecutor);

        $this->assertSame([[1, 0, 'first'], [1, 1, 'second'], [2, 1, 'other'], [3, 0, 'zero']], $this->rows());
    }

    public function testUpdateOfADtoMarkingPartOfTheKeyIsRefused(): void
    {
        try {
            (new RoundTripSlotPartialKeyDto(1, 1, 'renamed'))->update($this->aqlExecutor);
            $this->fail('An update filtered on roundTripId alone must be refused');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('roundTripId, position', $exception->getMessage());
        }

        $this->assertSame([[1, 0, 'first'], [1, 1, 'second'], [2, 1, 'other']], $this->rows());
    }

    public function testDeleteOfADtoMarkingPartOfTheKeyIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("does not fix every key column ('roundTripId, position')");

        try {
            (new RoundTripSlotPartialKeyDto(1, 1, 'second'))->delete($this->aqlExecutor);
        } finally {
            $this->assertSame([[1, 0, 'first'], [1, 1, 'second'], [2, 1, 'other']], $this->rows());
        }
    }

    public function testAWriteWithoutAValueForAKeyColumnIsRefused(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage("no value in its primary key property 'position'");

        try {
            (new RoundTripSlotNullableKeyDto(1, null, 'renamed'))->update($this->aqlExecutor);
        } finally {
            $this->assertSame([[1, 0, 'first'], [1, 1, 'second'], [2, 1, 'other']], $this->rows());
        }
    }

    public function testASingleColumnKeyIsFoundAndUpdatedByItsColumn(): void
    {
        $row = RoundTripDto::fetchById($this->aqlExecutor, 2);
        $this->assertSame('two', $row->value);

        $row->value = 'changed';
        $row->update($this->aqlExecutor);

        $this->assertSame([[1, 'one'], [2, 'changed']],
            $this->pdo->query('SELECT id, value FROM aql_round_trip ORDER BY id')->fetchAll(\PDO::FETCH_NUM));
    }

    public function testAnUpdateOfAnUninsertedSingleColumnKeyIsRefused(): void
    {
        $this->expectException(MappingException::class);
        (new RoundTripDto('never inserted'))->update($this->aqlExecutor);
    }

    public function testAQueryMarkedByPrimaryKeyNeedsEveryKeyColumn(): void
    {
        $query = (new AqlParser())
            ->parse('UPDATE AqlRoundTripSlot SET label = "renamed" WHERE roundTripId = 1 LIMIT 1');
        $this->assertInstanceOf(QueryInterface::class, $query);
        $query->getQueryOptions()->addOption(new QueryOption(QueryInterface::BY_PRIMARY_KEY, true, true));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("does not fix every key column ('roundTripId, position')");
        $this->aqlExecutor->executeAql($query);
    }

    public function testAKeyColumnOfAnotherEntityDoesNotCount(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("does not fix every key column ('id')");

        try {
            (new RoundTripLocalNoteForeignKeyDto(1, 'renamed'))->update($this->aqlExecutor);
        } finally {
            $this->assertSame([['a'], ['b']], $this->pdo
                ->query('SELECT label FROM aql_round_trip_local_note ORDER BY id')->fetchAll(\PDO::FETCH_NUM));
        }
    }

    public function testAnUnmarkedUpdateWithLimitStillRuns(): void
    {
        $query = (new AqlParser())
            ->parse('UPDATE AqlRoundTripSlot SET label = "claimed" WHERE roundTripId = 2 LIMIT 1');

        $this->assertSame(1, $this->aqlExecutor->executeAql($query)->getAffectedRows());
        $this->assertSame([[1, 0, 'first'], [1, 1, 'second'], [2, 1, 'claimed']], $this->rows());
    }

    public function testFindByIdOnACompositeKeyIsRefused(): void
    {
        $this->expectException(MappingException::class);
        RoundTripSlotDto::findById($this->aqlExecutor, 1);
    }

    public function testFindOneTakesAValueForEachKeyColumn(): void
    {
        $slot = RoundTripSlotDto::findOne($this->aqlExecutor, ['roundTripId' => 1, 'position' => 1]);

        $this->assertSame('second', $slot?->label);
    }

    /** @return list<array{int, int, string}> */
    private function rows(): array
    {
        $rows = $this->pdo->query('SELECT roundTripId, position, label FROM aql_round_trip_slot'
            . ' ORDER BY roundTripId, position')->fetchAll(\PDO::FETCH_NUM);

        return \array_map(static fn(array $row): array => [(int) $row[0], (int) $row[1], $row[2]], $rows);
    }
}
