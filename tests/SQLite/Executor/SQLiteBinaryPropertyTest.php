<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyBinary;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

/** SQLite cannot quote a NUL byte as text, so binary values reach it only as X'…' literals. */
final class SQLiteBinaryPropertyTest extends TestCaseWithSqlMemoryDb
{
    public function testBinaryValuesRoundTripByteExact(): void
    {
        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Hashed';
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty((new PropertyInteger('id'))->asPrimaryKey()->asAutoIncrement())
                    ->describeProperty(new PropertyBinary('hash', 32));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        });

        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();
        $dataset->instantiatePopulator('hashed')->setTemplateData([])->populate();

        // A NUL byte, 0xFF that never occurs in UTF-8, a quote and a backslash.
        $hash                       = "\x00\xff'\\" . \str_repeat("\xc3", 28);
        $other                      = \str_repeat("\x00", 32);

        $insert                     = (new AqlParser())->parse('INSERT INTO Hashed SET hash = {hash}');
        $insert->applyParameters(['hash' => $hash]);
        $this->assertSame(1, $this->getAqlExecutor()->executeAql($insert)->getAffectedRows());

        $insert                     = (new AqlParser())->parse('INSERT INTO Hashed (hash) VALUES ({hash})');
        $insert->applyParameters(['hash' => $other]);
        $this->assertSame(1, $this->getAqlExecutor()->executeAql($insert)->getAffectedRows());

        $insert                     = (new AqlParser())->parse('INSERT INTO Hashed (id, hash) VALUES (10, {hash}), (11, {other})');
        $insert->applyParameters(['hash' => $other, 'other' => $hash]);
        $this->assertSame(2, $this->getAqlExecutor()->executeAql($insert)->getAffectedRows());

        $select                     = (new AqlParser())->parse('SELECT hash FROM Hashed WHERE hash = {hash}');
        $select->applyParameters(['hash' => $hash]);

        $this->assertSame([['hash' => $hash], ['hash' => $hash]],
            $this->getAqlExecutor()->executeAql($select)->finalize()->toArray());

        $list                       = (new AqlParser())->parse(
            'SELECT hash FROM Hashed WHERE hash IN ({first}, {second}) ORDER BY id'
        );
        $list->applyParameters(['first' => $other, 'second' => $hash]);

        $this->assertSame([['hash' => $hash], ['hash' => $other], ['hash' => $other], ['hash' => $hash]],
            $this->getAqlExecutor()->executeAql($list)->finalize()->toArray());

        $all                        = (new AqlParser())->parse('SELECT hash FROM Hashed ORDER BY id');

        $this->assertSame([['hash' => $hash], ['hash' => $other], ['hash' => $other], ['hash' => $hash]],
            $this->getAqlExecutor()->executeAql($all)->finalize()->toArray());
    }
}
