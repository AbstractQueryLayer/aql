<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;
use IfCastle\Exceptions\LogicalException;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

/** A query rebuilt by fromArray() runs as the query it was written from, for each query kind. */
final class SQLiteQueryRoundTripTest extends TestCaseWithSqlMemoryDb
{
    public function testARestoredSelectReadsTheRowsOfTheOriginal(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse(
            'SELECT id FROM Pair WHERE (first = {value} OR second = {value}) AND id IN {ids} ORDER BY id DESC LIMIT 2'
        );
        $restored                   = $this->roundTrip($query);
        $query->applyParameters(['value' => 'x', 'ids' => [1, 2, 3]]);
        $restored->applyParameters(['value' => 'x', 'ids' => [1, 2, 3]]);

        $this->assertSame([2, 1], $this->selectIds($query));
        $this->assertSame([2, 1], $this->selectIds($restored));
    }

    public function testARestoredInsertAddsTheRow(): void
    {
        $this->createPairs();

        $insert                     = $this->roundTrip(
            (new AqlParser())->parse('INSERT INTO Pair (first, second) VALUES ({first}, "w")')
        );
        $insert->applyParameters(['first' => 'v']);
        $this->getAqlExecutor()->executeAql($insert);

        $this->assertSame([4], $this->selectIds((new AqlParser())->parse('SELECT id FROM Pair WHERE first = "v"')));
    }

    public function testARestoredUpdateChangesTheRow(): void
    {
        $this->createPairs();

        $update                     = $this->roundTrip(
            (new AqlParser())->parse('UPDATE Pair SET second = "u" WHERE id = {id}')
        );
        $update->applyParameters(['id' => 3]);
        $this->getAqlExecutor()->executeAql($update);

        $this->assertSame([3], $this->selectIds((new AqlParser())->parse('SELECT id FROM Pair WHERE second = "u"')));
    }

    public function testARestoredDeleteRemovesTheRow(): void
    {
        $this->createPairs();

        $delete                     = $this->roundTrip((new AqlParser())->parse('DELETE FROM Pair WHERE first = "z"'));
        $this->getAqlExecutor()->executeAql($delete);

        $this->assertSame([1], $this->selectIds((new AqlParser())->parse('SELECT id FROM Pair ORDER BY id')));
    }

    /** One stored form serves many runs: each restored copy runs with its own values. */
    public function testOneSerializedFormRunsTwiceWithDifferentValues(): void
    {
        $this->createPairs();

        $json                       = \json_encode(ArrayTyped::serialize(
            (new AqlParser())->parse('SELECT id FROM Pair WHERE first = {first} ORDER BY id')
        ), JSON_THROW_ON_ERROR);

        $this->assertSame([1], $this->selectIds($this->fromJson($json, ['first' => 'x'])));
        $this->assertSame([2, 3], $this->selectIds($this->fromJson($json, ['first' => 'z'])));
    }

    /** A run transforms the tree in place and binds it to entities, so the parsed form is written before the run. */
    public function testAQueryThatRanIsRefused(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse('SELECT id FROM Pair WHERE first = "x"');
        $this->selectIds($query);

        $this->expectException(LogicalException::class);
        $this->expectExceptionMessage('serialize the query before it runs');

        ArrayTyped::serialize($query);
    }

    private function roundTrip(BasicQueryInterface $query): BasicQueryInterface
    {
        return $this->fromJson(\json_encode(ArrayTyped::serialize($query), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed>|null $parameters */
    private function fromJson(string $json, ?array $parameters = null): BasicQueryInterface
    {
        $query                      = ArrayTyped::unserialize(\json_decode($json, true, flags: JSON_THROW_ON_ERROR));

        if ($parameters !== null) {
            $query->applyParameters($parameters);
        }

        return $query;
    }

    private function createPairs(): void
    {
        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Pair';
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty((new PropertyInteger('id'))->asPrimaryKey()->asAutoIncrement())
                    ->describeProperty(new PropertyString('first'))
                    ->describeProperty(new PropertyString('second'));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        });

        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();
        $dataset->instantiatePopulator('pair')
            ->setTemplateData([
                ['first' => 'x', 'second' => 'y'],
                ['first' => 'z', 'second' => 'x'],
                ['first' => 'z', 'second' => 'z'],
            ])
            ->populate();
    }

    /** @return list<int> */
    private function selectIds(BasicQueryInterface $query): array
    {
        $rows                       = $this->getAqlExecutor()->executeAql($query)->finalize()->toArray();

        return \array_map('intval', \array_column($rows, 'id'));
    }
}
