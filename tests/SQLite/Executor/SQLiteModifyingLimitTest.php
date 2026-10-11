<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

/** SQLite has no LIMIT in UPDATE and DELETE: such a query is refused, never widened to every matching row. */
final class SQLiteModifyingLimitTest extends TestCaseWithSqlMemoryDb
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Capped';
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty((new PropertyInteger('id'))->asPrimaryKey()->asAutoIncrement())
                    ->describeProperty(new PropertyString('name'));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        });

        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();
        $dataset->instantiatePopulator('capped')
            ->setTemplateData([['name' => 'a'], ['name' => 'b'], ['name' => 'c'], ['name' => 'd']])
            ->populate();
    }

    public function testDeleteWithOrderByAndLimitIsRefusedAndDeletesNothing(): void
    {
        $this->assertRefused('DELETE FROM Capped ORDER BY id LIMIT 2');
    }

    public function testDeleteWithLimitParameterIsRefusedAndDeletesNothing(): void
    {
        $this->assertRefused('DELETE FROM Capped WHERE id > 0 LIMIT {n}', ['n' => 1]);
    }

    public function testDeleteWithOrderByOnlyIsRefused(): void
    {
        $this->assertRefused('DELETE FROM Capped ORDER BY id', clause: 'ORDER BY');
    }

    public function testUpdateWithLimitIsRefusedAndChangesNothing(): void
    {
        $this->assertRefused('UPDATE Capped SET name = "z" LIMIT 1');

        $this->assertSame(['a', 'b', 'c', 'd'], $this->names());
    }

    public function testLimitOneOnANonKeyFilterIsRefused(): void
    {
        $this->assertRefused('DELETE FROM Capped WHERE name = "a" LIMIT 1');
    }

    public function testLimitOneOverAPrimaryKeyFilterRunsOnTheOneRow(): void
    {
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse('DELETE FROM Capped WHERE id = 2 LIMIT 1'));
        $this->getAqlExecutor()->executeAql(
            (new AqlParser())->parse('UPDATE Capped SET name = "z" WHERE id = 1 LIMIT 1')
        );

        $this->assertSame(['z', 'c', 'd'], $this->names());
    }

    public function testDeleteWithoutLimitStillDeletesTheMatchingRows(): void
    {
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse('DELETE FROM Capped WHERE id > 2'));

        $this->assertSame(['a', 'b'], $this->names());
    }

    public function testUpdateWithoutLimitStillChangesTheMatchingRows(): void
    {
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse('UPDATE Capped SET name = "z" WHERE id = 1'));

        $this->assertSame(['z', 'b', 'c', 'd'], $this->names());
    }

    private function assertRefused(string $aql, array $parameters = [], string $clause = 'LIMIT'): void
    {
        $query                      = (new AqlParser())->parse($aql);
        $query->applyParameters($parameters);

        try {
            $this->getAqlExecutor()->executeAql($query);
            $this->fail('A modifying query with LIMIT was executed on SQLite');
        } catch (QueryException $exception) {
            $this->assertStringContainsString($clause, $exception->getMessage());
        }

        $this->assertSame(['a', 'b', 'c', 'd'], $this->names());
    }

    private function names(): array
    {
        return \array_column($this->getAqlExecutor()->executeAql(
            (new AqlParser())->parse('SELECT name FROM Capped ORDER BY id')
        )->finalize()->toArray(), 'name');
    }
}
