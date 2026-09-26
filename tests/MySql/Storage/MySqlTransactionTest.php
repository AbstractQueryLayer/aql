<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Storage;

use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\Exceptions\ConnectFailed;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Transaction\IsolationLevelEnum;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\spawn;

/**
 * Transactions of one coroutine through the pooled MySql driver. Each result is read from a new coroutine
 * after the working one has ended, or from a connection outside the pool: the pool rolls back a
 * transaction left open when it takes a connection back, so an unsent COMMIT shows as a missing row.
 */
class MySqlTransactionTest extends TestCase
{
    private const string TABLE      = 'mysql_transaction_test';

    private \PDO $pdo;

    private MySql $mySql;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY) ENGINE=InnoDB');

        $this->mySql                = new MySql([
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
            'options'               => [\PDO::ATTR_POOL_MAX => 1],
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->mySql->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testCommittedRowIsVisibleFromANewCoroutine(): void
    {
        $this->inCoroutine(function (): void {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);
            $transaction->commit();
        });

        $this->assertSame([1], $this->idsFromNewCoroutine());
        $this->assertSame([1], $this->idsOutsideThePool());
    }

    public function testRolledBackRowIsAbsent(): void
    {
        // Read in the same coroutine as well: at its end the pool would roll back an unsent ROLLBACK.
        $idsAfterRollback           = $this->inCoroutine(function (): array {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);
            $transaction->rollBack();

            return $this->ids();
        });

        $this->assertSame([], $idsAfterRollback);
        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public function testTransactionsOneAfterAnotherOnTheSameDriver(): void
    {
        $this->inCoroutine(function (): void {
            $first                  = new Transaction();
            $this->insert(1, $first);
            $first->commit();

            $second                 = new Transaction();
            $this->insert(2, $second);
            $second->rollBack();

            $third                  = new Transaction();
            $this->insert(3, $third);
            $third->commit();
        });

        $this->assertSame([1, 3], $this->idsFromNewCoroutine());
    }

    public function testChildTransactionRollsBackToItsSavepoint(): void
    {
        $this->inCoroutine(function (): void {
            $parent                 = new Transaction();
            $this->insert(1, $parent);

            $child                  = (new Transaction())->setParentTransaction($parent);
            $this->insert(2, $child);
            $child->rollBack();

            $this->insert(3, $parent);
            $parent->commit();
        });

        $this->assertSame([1, 3], $this->idsFromNewCoroutine());
    }

    public function testChildBeginsItsParentWhenTheParentHasNotRunHereYet(): void
    {
        $this->inCoroutine(function (): void {
            $parent                 = new Transaction();
            $child                  = (new Transaction())->setParentTransaction($parent);
            $this->insert(1, $child);
            $child->commit();

            $this->insert(2, $parent);
            $parent->rollBack();
        });

        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public function testParentCommitsAfterItsChildRolledBack(): void
    {
        $this->inCoroutine(function (): void {
            $parent                 = new Transaction();
            $child                  = (new Transaction())->setParentTransaction($parent);
            $this->insert(1, $child);
            $this->insert(2, $parent);
            $child->rollBack();
            $parent->commit();
        });

        // The child's savepoint precedes both inserts, so its rollback takes the parent's row as well.
        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public function testTransactionWithoutParentInsideAnotherIsRefused(): void
    {
        $refused                    = $this->inCoroutine(function (): ?StorageException {
            $outer                  = new Transaction();
            $this->insert(1, $outer);

            try {
                $this->insert(2, new Transaction());
            } catch (StorageException $exception) {
                return $exception;
            } finally {
                $outer->commit();
            }

            return null;
        });

        $this->assertInstanceOf(StorageException::class, $refused);
        $this->assertSame([1], $this->idsFromNewCoroutine());
    }

    public function testUnfinishedTransactionDoesNotReachTheNextCoroutine(): void
    {
        // The coroutine ends with its transaction open; the pool rolls the connection back.
        $this->inCoroutine(fn() => $this->insert(1, new Transaction()));

        $idsAfterRollback           = $this->inCoroutine(function (): array {
            $rolledBack             = new Transaction();
            $this->insert(2, $rolledBack);
            $rolledBack->rollBack();

            $committed              = new Transaction();
            $this->insert(3, $committed);
            $committed->commit();

            return $this->ids();
        });

        $this->assertSame([3], $idsAfterRollback);
    }

    public function testChildOfATransactionLeftInAnotherCoroutineIsRefused(): void
    {
        $abandoned                  = new Transaction();
        $this->inCoroutine(fn() => $this->insert(1, $abandoned));

        $refused                    = $this->inCoroutine(function () use ($abandoned): ?StorageException {
            try {
                $this->insert(2, (new Transaction())->setParentTransaction($abandoned));
            } catch (StorageException $exception) {
                return $exception;
            }

            return null;
        });

        $this->assertInstanceOf(StorageException::class, $refused);
        $this->assertSame([], $this->idsFromNewCoroutine());
    }

    public function testIsolationLevelAppliesToItsTransactionOnly(): void
    {
        $levels                     = $this->inCoroutine(function (): array {
            $transaction            = new Transaction(null, IsolationLevelEnum::SERIALIZABLE);
            $context                = $this->context($transaction);

            // InnoDB starts the transaction on its first read, and only then lists it in INNODB_TRX.
            $this->mySql->executeSql('SELECT COUNT(*) FROM ' . self::TABLE, $context);
            $level                  = $this->mySql->executeSql(
                'SELECT trx_isolation_level AS level FROM information_schema.INNODB_TRX'
                . ' WHERE trx_mysql_thread_id = CONNECTION_ID()',
                $context
            )->toArray();
            $transaction->commit();

            return $level;
        });

        // POOL_MAX is 1, so the next coroutine gets the same connection back.
        $session                    = $this->inCoroutine(
            fn() => $this->mySql->executeSql('SELECT @@session.transaction_isolation AS level')->toArray()
        );

        $this->assertSame([['level' => 'SERIALIZABLE']], $levels);
        $this->assertSame([['level' => 'REPEATABLE-READ']], $session);
    }

    public function testStatementOfATransactionTheServerEndedIsRefused(): void
    {
        [$refused, $idsAfter]       = $this->inCoroutine(function (): array {
            $transaction            = new Transaction();
            $this->insert(1, $transaction);

            // DDL commits the open transaction implicitly; the next insert would autocommit.
            $this->mySql->executeSql('DROP TABLE IF EXISTS ' . self::TABLE . '_ddl', $this->context($transaction));

            try {
                $this->insert(2, $transaction);
            } catch (StorageException $exception) {
                return [$exception, $this->ids()];
            }

            return [null, $this->ids()];
        });

        $this->assertInstanceOf(StorageException::class, $refused);
        $this->assertSame([1], $idsAfter);
    }

    public function testDisconnectedStorageDoesNotOpenAnotherPool(): void
    {
        $this->mySql->disconnect();

        $this->expectException(ConnectFailed::class);

        $this->inCoroutine(fn() => $this->mySql->executeSql('SELECT 1'));
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

    private function inCoroutine(callable $work): mixed
    {
        return await(spawn($work));
    }

    /**
     * @return list<int>
     */
    private function idsFromNewCoroutine(): array
    {
        return $this->inCoroutine($this->ids(...));
    }

    /**
     * @return list<int>
     */
    private function ids(): array
    {
        $rows                       = $this->mySql->executeSql('SELECT id FROM ' . self::TABLE . ' ORDER BY id')->toArray();

        return \array_map(intval(...), \array_column($rows, 'id'));
    }

    /**
     * @return list<int>
     */
    private function idsOutsideThePool(): array
    {
        return \array_map(intval(...), $this->pdo->query('SELECT id FROM ' . self::TABLE . ' ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
    }
}
