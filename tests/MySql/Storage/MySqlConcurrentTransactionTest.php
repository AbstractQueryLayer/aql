<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Storage;

use Async\Channel;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SqlStatementInterface;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\AQL\Transaction\TransactionStatusEnum;
use IfCastle\Exceptions\CompositeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function Async\await;
use function Async\current_coroutine;
use function Async\delay;
use function Async\spawn;

/**
 * Transactions of several coroutines through one pooled MySql driver. Each coroutine has a connection
 * of its own, and the transactions open on it are known to that coroutine only.
 */
class MySqlConcurrentTransactionTest extends TestCase
{
    private const string TABLE      = 'mysql_concurrent_transaction_test';

    private const int POOL_MAX      = 4;

    // Milliseconds each coroutine waits inside its transaction, so that the transactions overlap.
    private const int OVERLAP_MS    = 50;

    // Rows the other side of the deadlock changes, so that InnoDB picks the driver's transaction as victim.
    private const int HEAVY_ROWS    = 100;

    // Bound the stress run; reuse of an object handle is not required by the runtime.
    private const int MAX_ABANDONED_COROUTINES = 20;

    // Ids of rows inserted by transactions left open when their coroutine ends; the pool rolls them back.
    private const int ABANDONED_ID  = 1000;

    private \PDO $pdo;

    private MySql $mySql;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY) ENGINE=InnoDB');

        $this->mySql                = $this->newMySql();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->mySql->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testOverlappingTransactionsFinishIndependently(): void
    {
        $committing                 = spawn(function (): int {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);
            delay(self::OVERLAP_MS);
            $transaction->commit();

            return current_coroutine()->getId();
        });

        $rollingBack                = spawn(function (): int {
            $transaction            = new Transaction();
            $this->insert(2, $transaction);
            delay(self::OVERLAP_MS);
            $transaction->rollBack();

            return current_coroutine()->getId();
        });

        $throwing                   = spawn(function (): never {
            $this->insert(3, new Transaction());
            delay(self::OVERLAP_MS);

            throw new \RuntimeException('failed inside the transaction');
        });

        // Awaited first, so that its exception reaches a waiting await() rather than the scope.
        try {
            await($throwing);
            $this->fail('The throwing coroutine did not throw');
        } catch (\RuntimeException) {
        }

        await($committing);
        await($rollingBack);

        // Later coroutines run independently, including one with a reused id if the runtime gives one.
        $this->assertNull(await(spawn(fn() => $this->mySql->getTransaction())), 'no transaction before begin');
        $this->inCoroutine(function (): void {
            $transaction            = new Transaction();
            $this->insert(4, $transaction);
            $transaction->commit();
        });

        $completedId                = $this->transactionAfterAbandonedCoroutines(5);

        $this->assertNotNull($completedId, 'a later coroutine commits its own transaction');
        $this->assertSame([1, 4, 5], $this->idsFromNewCoroutine());
    }

    public function testTransactionOfOneCoroutineIsNotTheTransactionOfAnother(): void
    {
        $held                       = new Channel(1);
        $release                    = new Channel(1);

        $holder                     = spawn(function () use ($held, $release): void {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);
            $held->send($transaction);
            $release->recv();
            $transaction->commit();
        });

        $foreign                    = $held->recv();

        [$seen, $childRefused, $statementRefused, [$finishRefused, $statusAfterRefusal]] = $this->inCoroutine(function () use ($foreign): array {
            $seen                   = $this->mySql->getTransaction();

            // This coroutine has a transaction of its own, so a SAVEPOINT would land inside it.
            $own                    = new Transaction();
            $this->insert(10, $own);

            $childRefused           = $this->failureOf(
                fn() => $this->insert(2, (new Transaction())->setParentTransaction($foreign))
            );
            $statementRefused       = $this->failureOf(fn() => $this->insert(3, $foreign));
            $own->rollBack();

            $finishRefused          = $this->failureOf(fn() => $foreign->commit());

            return [$seen, $childRefused, $statementRefused, [$finishRefused, $foreign->getStatus()]];
        });

        $release->send(true);
        await($holder);

        $this->assertNull($seen);
        $this->assertInstanceOf(QueryException::class, $childRefused);
        $this->assertInstanceOf(QueryException::class, $statementRefused);
        $this->assertInstanceOf(QueryException::class, $finishRefused);
        $this->assertSame(TransactionStatusEnum::OPENED, $statusAfterRefusal, 'a refused finish changes nothing');
        $this->assertSame([1], $this->idsFromNewCoroutine(), 'the holder commits after the refusal elsewhere');
    }

    public function testRefusedRollbackKeepsTheErrorItWasGiven(): void
    {
        $held                       = new Channel(1);
        $release                    = new Channel(1);

        $holder                     = spawn(function () use ($held, $release): void {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);
            $held->send($transaction);
            $release->recv();
            $transaction->rollBack();
        });

        $foreign                    = $held->recv();
        $cause                      = new \RuntimeException('the cause of the rollback');

        $thrown                     = $this->inCoroutine(static function () use ($foreign, $cause): ?\Throwable {
            try {
                $foreign->rollBack($cause);
            } catch (\Throwable $thrown) {
                return $thrown;
            }

            return null;
        });

        $release->send(true);
        await($holder);

        $this->assertInstanceOf(CompositeException::class, $thrown);
        $this->assertSame($cause, $thrown->getPrevious());
    }

    public function testTransactionOfAnEndedCoroutineRollsBackButDoesNotCommit(): void
    {
        $rolledBack                 = new Transaction();
        $committed                  = new Transaction();
        $this->inCoroutine(function () use ($rolledBack, $committed): void {
            $this->insert(1, $rolledBack);
        });
        $this->inCoroutine(function () use ($committed): void {
            $this->insert(2, $committed);
        });

        // The pool rolled both back when their coroutines ended.
        $rollBack                   = $this->inCoroutine(fn() => $this->failureOf($rolledBack->rollBack(...)));
        $commit                     = $this->inCoroutine(fn() => $this->failureOf($committed->commit(...)));

        $this->assertNull($rollBack);
        $this->assertSame(TransactionStatusEnum::ROLLED_BACK, $rolledBack->getStatus());
        $this->assertInstanceOf(QueryException::class, $commit);
        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public function testTransactionTheServerRolledBackOnDeadlockIsNotContinued(): void
    {
        $this->pdo->exec('INSERT INTO ' . self::TABLE . ' (id) SELECT seq FROM seq_1_to_' . self::HEAVY_ROWS);

        // The other side locks row 1, then asks for row 2, which the victim holds while it waits for
        // row 1. The HEAVY_ROWS rows the other side changes make the victim the lighter transaction.
        $this->pdo->beginTransaction();
        $this->pdo->exec('UPDATE ' . self::TABLE . ' SET id = id + 1000 WHERE id > 2');
        $this->pdo->exec('DELETE FROM ' . self::TABLE . ' WHERE id = 1');

        $victim                     = spawn(function (): array {
            $transaction            = new Transaction();
            $context                = $this->context($transaction);
            $this->mySql->executeSql('DELETE FROM ' . self::TABLE . ' WHERE id = 2', $context);
            $deadlock               = $this->failureOf(
                fn() => $this->mySql->executeSql('DELETE FROM ' . self::TABLE . ' WHERE id = 1', $context)
            );

            // InnoDB rolled the whole transaction back: a further statement would commit on its own.
            $continued              = $this->failureOf(fn() => $this->insert(self::HEAVY_ROWS + 1, $transaction));
            $rollBack               = $this->failureOf($transaction->rollBack(...));

            $next                   = new Transaction();
            $this->insert(self::HEAVY_ROWS + 2, $next);
            $next->commit();

            return [$deadlock, $continued, $rollBack];
        });

        delay(self::OVERLAP_MS);
        $this->pdo->exec('DELETE FROM ' . self::TABLE . ' WHERE id = 2');
        $this->pdo->rollBack();

        [$deadlock, $continued, $rollBack] = await($victim);

        $this->assertInstanceOf(RecoverableException::class, $deadlock);
        $this->assertInstanceOf(StorageException::class, $continued);
        $this->assertNull($rollBack, 'rolling back what the server rolled back is not an error');
        $this->assertNotContains(self::HEAVY_ROWS + 1, $this->idsFromNewCoroutine());
        $this->assertContains(self::HEAVY_ROWS + 2, $this->idsFromNewCoroutine());
    }

    public function testFailedCommitLeavesNoTransactionOnTheConnection(): void
    {
        $mySql                      = new class ([
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
            'options'               => [\PDO::ATTR_POOL_MAX => 1],
        ]) extends MySql {
            #[\Override]
            protected function realCommit(): void
            {
                throw new QueryException('COMMIT failed', 'COMMIT');
            }
        };

        [$failure, $inTransaction]  = $this->inCoroutine(function () use ($mySql): array {
            $transaction            = new Transaction();
            $mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (1)', $this->context($transaction));
            $failure                = $this->failureOf($transaction->commit(...));

            return [$failure, $mySql->executeSql('SELECT @@in_transaction AS open')->toArray()[0]['open']];
        });

        $mySql->dispose();

        $this->assertInstanceOf(QueryException::class, $failure);
        $this->assertSame(0, (int) $inTransaction);
        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public static function failedFinishes(): array
    {
        return [
            'rollback' => ['rollback'],
            'PDO returns false' => ['silent rollback'],
            'commit and cleanup rollback' => ['commit'],
            'release savepoint' => ['release'],
            'rollback to savepoint' => ['savepoint rollback'],
        ];
    }

    #[DataProvider('failedFinishes')]
    public function testFailedFinishDoesNotAllowUntrackedWrites(string $operation): void
    {
        $mySql = new class ([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 2],
        ]) extends MySql {
            public string $failureOperation;

            #[\Override]
            protected function realCommit(): void
            {
                if ($this->failureOperation === 'commit') {
                    throw new QueryException('COMMIT failed', 'COMMIT');
                }

                parent::realCommit();
            }

            #[\Override]
            protected function realRollback(): void
            {
                if ($this->failureOperation === 'silent rollback') {
                    $this->transactionCall(static fn(): bool => false, 'ROLLBACK');
                    return;
                }

                throw new QueryException('ROLLBACK failed', 'ROLLBACK');
            }

            #[\Override]
            protected function realExecuteQuery(string $sql): ResultInterface
            {
                if (($this->failureOperation === 'release' && \str_starts_with($sql, 'RELEASE SAVEPOINT'))
                    || ($this->failureOperation === 'savepoint rollback' && \str_starts_with($sql, 'ROLLBACK TO SAVEPOINT'))) {
                    throw new QueryException('Savepoint finish failed', $sql);
                }

                return parent::realExecuteQuery($sql);
            }
        };
        $mySql->failureOperation = $operation;

        try {
            [$failure, $writeFailure, $parentFailure] = $this->inCoroutine(function () use ($mySql, $operation): array {
                $transaction = new Transaction();
                $mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (1)', $this->context($transaction));
                $child = null;

                if ($operation === 'release' || $operation === 'savepoint rollback') {
                    $child = (new Transaction())->setParentTransaction($transaction);
                    $mySql->executeSql('SELECT 1', $this->context($child));
                }

                $finishing = $child ?? $transaction;
                $failure = $this->failureOf(
                    $operation === 'commit' || $operation === 'release' ? $finishing->commit(...) : $finishing->rollBack(...)
                );
                $writeFailure = $this->failureOf(
                    fn() => $mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (2)')
                );
                $parentFailure = $child === null ? null : $this->failureOf($transaction->commit(...));
                if ($child !== null) {
                    $this->assertSame(TransactionStatusEnum::OPENED, $transaction->getStatus());
                }
                $this->assertInstanceOf(QueryException::class, $this->failureOf(fn() => $mySql->createStatement('SELECT 1')));
                $statement = $this->createMock(SqlStatementInterface::class);
                $this->assertInstanceOf(QueryException::class, $this->failureOf(fn() => $mySql->executeStatement($statement)));
                $this->assertInstanceOf(QueryException::class, $this->failureOf(fn() => $mySql->beginTransaction(new Transaction())));

                // A separate coroutine keeps using the same pool while the failed owner is alive.
                $this->inCoroutine(fn() => $mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (3)'));

                return [$failure, $writeFailure, $parentFailure];
            });

            $this->assertInstanceOf(QueryException::class, $failure);
            $this->assertInstanceOf(QueryException::class, $writeFailure, 'an uncertain transaction cannot accept more SQL');
            if ($operation === 'release' || $operation === 'savepoint rollback') {
                $this->assertInstanceOf(QueryException::class, $parentFailure);
            }

            $this->inCoroutine(fn() => $mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (4)'));
            $this->assertSame([3, 4], $this->idsFromNewCoroutine());
        } finally {
            $mySql->dispose();
        }
    }

    /**
     * Runs coroutines after abandoned transactions, committing when a handle is reused or at the end
     * of the stress run. Every coroutine must start with no transaction regardless of handle allocation.
     */
    private function transactionAfterAbandonedCoroutines(int $id): ?int
    {
        $seenIds                    = [];

        for ($i = 0; $i < self::MAX_ABANDONED_COROUTINES; $i++) {
            $reusedId               = $this->inCoroutine(function () use (&$seenIds, $id, $i): ?int {
                $coroutineId        = current_coroutine()->getId();
                $this->assertNull($this->mySql->getTransaction(), 'a new coroutine sees no transaction');

                if ($i < self::MAX_ABANDONED_COROUTINES - 1 && false === \in_array($coroutineId, $seenIds, true)) {
                    $seenIds[]      = $coroutineId;
                    $this->insert(self::ABANDONED_ID + $i, new Transaction());

                    return null;
                }

                $transaction        = new Transaction();
                $this->insert($id, $transaction);
                $transaction->commit();

                return $coroutineId;
            });

            if ($reusedId !== null) {
                return $reusedId;
            }

            // Exercise handle reuse after collecting abandoned PHP transaction cycles.
            // Context reclamation itself is checked by the TrueAsync regression tests.
            \gc_collect_cycles();
        }

        return null;
    }

    private function newMySql(): MySql
    {
        return new MySql([
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
            'options'               => [\PDO::ATTR_POOL_MAX => self::POOL_MAX],
        ]);
    }

    private function insert(int $id, TransactionInterface $transaction): void
    {
        $this->mySql->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (' . $id . ')', $this->context($transaction));
    }

    private function context(TransactionInterface $transaction): TransactionAwareInterface
    {
        return new readonly class ($transaction) implements TransactionAwareInterface {
            public function __construct(private TransactionInterface $transaction) {}

            #[\Override]
            public function getTransaction(): ?TransactionInterface
            {
                return $this->transaction;
            }
        };
    }

    private function failureOf(callable $work): ?StorageException
    {
        try {
            $work();
        } catch (StorageException $exception) {
            return $exception;
        }

        return null;
    }

    private function inCoroutine(callable $work): mixed
    {
        return await(spawn($work));
    }

    /**
     * @return list<int>
     */
    private function idsFromNewCoroutine(): array
    {
        $rows                       = $this->inCoroutine(
            fn() => $this->mySql->executeSql('SELECT id FROM ' . self::TABLE . ' ORDER BY id')->toArray()
        );

        return \array_map(intval(...), \array_column($rows, 'id'));
    }
}
