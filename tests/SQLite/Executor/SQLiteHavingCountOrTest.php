<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

/** HAVING after GROUP BY, a top-level COUNT, a WHERE of ORs and SELECT DISTINCT, parsed from AQL and run on SQLite. */
final class SQLiteHavingCountOrTest extends TestCaseWithSqlMemoryDb
{
    public function testHavingFiltersTheGroups(): void
    {
        $this->createPairs();

        $this->assertSame([2, 3], $this->column('SELECT id FROM Pair GROUP BY id HAVING id > 1 ORDER BY id', 'id'));
        $this->assertSame(['z'], $this->column(
            'SELECT first, COUNT(*) AS n FROM Pair GROUP BY first HAVING COUNT(*) > 1 ORDER BY first', 'first'
        ));
    }

    public function testCountReturnsTheNumberOfMatchingRows(): void
    {
        $this->createPairs();

        $this->assertSame([2], $this->column('COUNT FROM Pair WHERE first = "z"', 'COUNT'));
    }

    public function testWhereOfOrsReadsEitherMatch(): void
    {
        $this->createPairs();

        $this->assertSame([1, 2], $this->column(
            'SELECT id FROM Pair WHERE first = "x" OR second = "x" ORDER BY id', 'id'
        ));
    }

    public function testDistinctKeepsEachValueOnce(): void
    {
        $this->createPairs();

        $this->assertSame(['x', 'z'], $this->column('SELECT DISTINCT first FROM Pair ORDER BY first', 'first'));
        $this->assertSame(['z'], $this->column('SELECT DISTINCT first FROM Pair WHERE id > 1', 'first'));
    }

    /** @return list<int|string> */
    private function column(string $aql, string $column): array
    {
        $rows                       = $this->getAqlExecutor()->executeAql((new AqlParser())->parse($aql))
            ->finalize()->toArray();

        return \array_map(
            static fn(mixed $value): int|string => \is_numeric($value) ? (int) $value : $value,
            \array_column($rows, $column)
        );
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
}
