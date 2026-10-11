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
use PHPUnit\Framework\Attributes\DataProvider;

/** SQLite has no row-level locks: a locking read is refused, never run as a plain read. */
final class SQLiteLockingReadTest extends TestCaseWithSqlMemoryDb
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Locked';
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
        $dataset->instantiatePopulator('locked')->setTemplateData([['name' => 'a']])->populate();
    }

    public static function lockClauses(): array
    {
        return ['for update' => ['FOR UPDATE'], 'share mode' => ['LOCK IN SHARE MODE']];
    }

    #[DataProvider('lockClauses')]
    public function testLockingReadIsRefused(string $clause): void
    {
        $query                      = (new AqlParser())->parse('SELECT name FROM Locked WHERE id = 1 ' . $clause);

        try {
            $this->getAqlExecutor()->executeAql($query);
            $this->fail('A locking read was executed on SQLite');
        } catch (QueryException $exception) {
            $this->assertStringContainsString($clause, $exception->getMessage());
        }
    }

    public static function misplacedLocks(): array
    {
        return [
            'union member'          => ['SELECT name FROM Locked UNION SELECT name FROM Locked FOR UPDATE'],
            'subquery in a filter'  => [
                'SELECT name FROM Locked WHERE id IN (SELECT id FROM Locked LOCK IN SHARE MODE)',
            ],
        ];
    }

    /** A lock below the top-level SELECT must not slip past the refusal, which looks at the top-level query. */
    #[DataProvider('misplacedLocks')]
    public function testLockOutsideTheTopLevelSelectIsRefused(string $aql): void
    {
        $query                      = (new AqlParser())->parse($aql);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('top-level');

        $this->getAqlExecutor()->executeAql($query);
    }

    public function testOrdinaryReadStillRuns(): void
    {
        $query                      = (new AqlParser())->parse('SELECT name FROM Locked WHERE id = 1');

        $this->assertSame([['name' => 'a']], $this->getAqlExecutor()->executeAql($query)->finalize()->toArray());
    }
}
