<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyJson;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

/** A property that unserializes its column changes the selected rows. */
final class SQLiteJsonPropertyTest extends TestCaseWithSqlMemoryDb
{
    public function testJsonColumnIsReadAsArray(): void
    {
        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Document';
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty((new PropertyInteger('id'))->asPrimaryKey()->asAutoIncrement())
                    ->describeProperty(new PropertyJson('data'));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        });

        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();
        $dataset->instantiatePopulator('document')->setTemplateData([['data' => '{"pages":[1,2]}']])->populate();

        $rows                       = $this->getAqlExecutor()->executeAql((new AqlParser())->parse(
            'SELECT data FROM Document'
        ))->finalize()->toArray();

        $this->assertSame([['data' => ['pages' => [1, 2]]]], $rows);
    }
}
