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

/** SQLite has no index hints: a hinted query runs without them and returns the same rows. */
final class SQLiteIndexHintTest extends TestCaseWithSqlMemoryDb
{
    public function testHintedQueryRunsWithoutTheHint(): void
    {
        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Hinted';
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
        $dataset->instantiatePopulator('hinted')
            ->setTemplateData([['name' => 'one'], ['name' => 'two'], ['name' => 'three']])
            ->populate();

        $rows                       = $this->getAqlExecutor()->executeAql((new AqlParser())->parse(
            'SELECT h.name FROM Hinted h FORCE INDEX (PRIMARY) IGNORE INDEX (idx_name) WHERE h.id > 1 ORDER BY h.id'
        ))->finalize()->toArray();

        $this->assertSame([['name' => 'two'], ['name' => 'three']], $rows);
    }
}
