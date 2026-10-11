<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;
use IfCastle\Exceptions\UnexpectedMethodMode;

/**
 * Values in WHERE: repeated named parameters share a value, unnamed ones keep their own, a parameter without a value
 * fails, IN lists keep their commas.
 */
final class SQLiteWhereValuesTest extends TestCaseWithSqlMemoryDb
{
    public function testRepeatedParameterMatchesBothColumns(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse(
            'SELECT id FROM Pair WHERE (first = {value} OR second = {value}) ORDER BY id'
        );
        $query->applyParameters(['value' => 'x']);

        $this->assertSame([1, 2], $this->selectIds($query));
    }

    public function testUnnamedParametersKeepTheirOwnValues(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse('SELECT id FROM Pair ORDER BY id');
        $query->setWhere((new Where())->equal('first', 'z')->equal('second', 'x'));

        $this->assertSame([2], $this->selectIds($query));
    }

    public function testInListOfLiteralsAndParameters(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse(
            'SELECT id FROM Pair WHERE second IN ("y", {value}) ORDER BY id'
        );
        $query->applyParameters(['value' => 'z']);

        $this->assertSame([1, 3], $this->selectIds($query));
        $this->assertSame([2], $this->selectIds((new AqlParser())->parse('SELECT id FROM Pair WHERE id IN (0, 2) ORDER BY id')));
    }

    public function testInListFromOneParameterWithAnArrayValue(): void
    {
        $this->createPairs();

        $query                      = (new AqlParser())->parse('SELECT id FROM Pair WHERE id IN ({ids}) ORDER BY id');
        $query->applyParameters(['ids' => [1, 3]]);
        $strings                    = (new AqlParser())->parse('SELECT id FROM Pair WHERE second IN ({values}) ORDER BY id');
        $strings->applyParameters(['values' => ['y', 'z']]);

        $this->assertSame([1, 3], $this->selectIds($query));
        $this->assertSame([1, 3], $this->selectIds($strings));
    }

    /** Rendered as NULL, a forgotten value would silently match nothing. */
    public function testParameterWithoutValueFailsTheQuery(): void
    {
        $this->createPairs();

        $this->expectException(UnexpectedMethodMode::class);

        $this->selectIds((new AqlParser())->parse('SELECT id FROM Pair WHERE first = {value}'));
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
