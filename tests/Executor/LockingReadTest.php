<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\MySql\Executor\MySqlQueryExecutor;
use IfCastle\AQL\PostgreSql\Executor\PostgreSqlQueryExecutor;
use IfCastle\AQL\Transaction\Transaction;
use PHPUnit\Framework\Attributes\DataProvider;

/** Where a locking read may run: inside a transaction, as the top-level SELECT, with no secondary queries. */
final class LockingReadTest extends SqlQueryExecutorTestCase
{
    public function testLockingReadRunsInsideATransaction(): void
    {
        $this->executeWithTransaction(new SqlQueryExecutor(), 'SELECT id FROM Book WHERE id = 1 FOR UPDATE');

        $this->assertStringEndsWith(' FOR UPDATE', $this->getSqlStorageMock()->getLastSql());
    }

    public static function lockClauses(): array
    {
        return ['for update' => ['FOR UPDATE'], 'share mode' => ['LOCK IN SHARE MODE']];
    }

    #[DataProvider('lockClauses')]
    public function testLockingReadOutsideATransactionIsRefused(string $clause): void
    {
        $query                      = (new AqlParser())->parse('SELECT id FROM Book WHERE id = 1 ' . $clause);

        try {
            $this->newSqlQueryExecutor()->executeQuery($query);
            $this->fail('A locking read without a transaction was executed');
        } catch (QueryException $exception) {
            $this->assertStringContainsString($clause, $exception->getMessage());
            $this->assertStringContainsString('transaction', $exception->getMessage());
        }

        $this->assertSame([], $this->getSqlStorageMock()->executedSql);
    }

    public function testOrdinaryReadOutsideATransactionStillRuns(): void
    {
        $query                      = (new AqlParser())->parse('SELECT id FROM Book WHERE id = 1');

        $this->newSqlQueryExecutor()->executeQuery($query);

        $this->assertNotNull($this->getSqlStorageMock()->getLastSql());
    }

    public static function queriesWithSecondaryQueries(): array
    {
        return [
            'virtual column read by a second query'     => ['SELECT id, authorList FROM Book FOR UPDATE'],
            'entity of another storage in the filter'   => [
                'SELECT * FROM BookFiles INNER JOIN Book WHERE Book.title = "The Great Gatsby" FOR UPDATE',
            ],
            'entity of another storage in the join'     => ['SELECT * FROM BookFiles INNER JOIN Book FOR UPDATE'],
        ];
    }

    #[DataProvider('queriesWithSecondaryQueries')]
    public function testLockingReadWithASecondaryQueryIsRefused(string $aql): void
    {
        try {
            $this->executeWithTransaction(new SqlQueryExecutor(), $aql);
            $this->fail('A lock was dropped by a secondary query');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOR UPDATE', $exception->getMessage());
            $this->assertStringContainsString('secondary', $exception->getMessage());
        }
    }

    public static function misplacedLocks(): array
    {
        return [
            'union member'                  => ['SELECT id FROM Book UNION SELECT id FROM Book FOR UPDATE'],
            'union head'                    => ['SELECT id FROM Book FOR UPDATE UNION SELECT id FROM Book'],
            'subquery in the filter'        => [
                'SELECT id FROM Book WHERE id IN (SELECT id FROM Book FOR UPDATE)',
            ],
            'share mode in a subquery'      => [
                'SELECT id FROM Book WHERE id IN (SELECT id FROM Book LOCK IN SHARE MODE)',
            ],
        ];
    }

    #[DataProvider('misplacedLocks')]
    public function testLockOutsideTheTopLevelSelectIsRefused(string $aql): void
    {
        try {
            $this->executeWithTransaction(new SqlQueryExecutor(), $aql);
            $this->fail('A lock outside the top-level SELECT was executed');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('top-level', $exception->getMessage());
        }

        $this->assertSame([], $this->getSqlStorageMock()->executedSql);
    }

    public function testOneSelectIsRenderedByPostgreSqlAndThenByMySql(): void
    {
        $select                     = (new AqlParser())->parse('SELECT id FROM Book WHERE id = 1 LOCK IN SHARE MODE');

        $this->executeParsedWithTransaction(new PostgreSqlQueryExecutor(), $select);
        $this->assertStringEndsWith(' FOR SHARE', $this->getSqlStorageMock()->getLastSql());

        $this->assertInstanceOf(SelectInterface::class, $select);
        $this->assertStringEndsWith(' LOCK IN SHARE MODE', $select->getAql());
        $this->assertStringEndsWith(' LOCK IN SHARE MODE', $select->getResultAsString());

        $this->executeParsedWithTransaction(new MySqlQueryExecutor(), $select);
        $this->assertStringEndsWith(' LOCK IN SHARE MODE', $this->getSqlStorageMock()->getLastSql());
    }

    private function executeWithTransaction(QueryExecutorInterface $executor, string $aql): void
    {
        $this->executeParsedWithTransaction($executor, (new AqlParser())->parse($aql));
    }

    private function executeParsedWithTransaction(QueryExecutorInterface $executor, object $query): void
    {
        $executor->resolveDependencies($this->getDiContainer());
        $executor->executeQuery($query, new AdditionalOptions([], new Transaction()));
    }
}
