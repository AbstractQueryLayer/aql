<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DI\Container;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/**
 * A pool of MySql storages in a StorageCollectionPool: MySql pools its own connections, so every coroutine borrows
 * the one shared driver and works on a connection of its own from the driver's pool.
 */
final class StorageCollectionPoolMariaDbTest extends TestCase
{
    private const string TABLE      = 'storage_collection_pool_test';

    private const int COROUTINES    = 4;

    // The native pool of the driver, larger than the number of coroutines.
    private const int POOL_MAX      = 8;

    // Milliseconds each coroutine waits inside its transaction, so that the transactions overlap.
    private const int OVERLAP_MS    = 50;

    private \PDO $pdo;

    private int $created            = 0;

    /** @var list<MySql> */
    private array $drivers          = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY) ENGINE=InnoDB');
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->drivers as $driver) {
            $driver->dispose();
        }

        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    /**
     * More coroutines than the storage pool may lend at once each find the storage: they all get the one driver, each
     * on its own connection in its own transaction, and a rollback of one leaves the commits of the others.
     */
    public function testCoroutinesShareTheDriverAndOwnTheirConnections(): void
    {
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies(
            new Container(new Resolver(), [ContextVariablesInterface::class => new CoroutineContextVariables()])
        );
        $collection->addStoragePool('main', new StoragePool($this->mySqlFactory(), 1));

        $coroutines                 = [];

        for ($id = 1; $id <= self::COROUTINES; $id++) {
            $coroutines[]           = spawn(function () use ($collection, $id): array {
                $storage            = $collection->findStorage('main');
                $this->assertInstanceOf(MySql::class, $storage);

                $transaction        = new Transaction();
                $context            = $this->context($transaction);
                $storage->executeSql('INSERT INTO ' . self::TABLE . ' (id) VALUES (' . $id . ')', $context);
                $connection         = $storage->executeSql('SELECT CONNECTION_ID() AS id', $context)->toArray();
                delay(self::OVERLAP_MS);
                $own                = $storage->getTransaction() === $transaction;

                $id % 2 === 0 ? $transaction->rollBack() : $transaction->commit();

                return [$storage, (int) $connection[0]['id'], $own];
            });
        }

        $results                    = \array_map(static fn($coroutine): array => await($coroutine), $coroutines);

        $this->assertSame(1, $this->created, 'the driver is built once');
        $drivers                    = \array_map(static fn(array $result): int => \spl_object_id($result[0]), $results);
        $this->assertCount(1, \array_unique($drivers), 'every coroutine gets the one driver');
        $this->assertCount(self::COROUTINES, \array_unique(\array_column($results, 1)), 'a connection per coroutine');
        $this->assertSame(\array_fill(0, self::COROUTINES, true), \array_column($results, 2), 'its own transaction');
        $this->assertSame([1, 3], \array_map(intval(...), \array_column(
            $this->pdo->query('SELECT id FROM ' . self::TABLE . ' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC),
            'id'
        )));
    }

    private function mySqlFactory(): FactoryInterface
    {
        return new readonly class ($this->newMySql(...)) implements FactoryInterface {
            public function __construct(private \Closure $build) {}

            #[\Override]
            public function createObject(): object
            {
                return ($this->build)();
            }
        };
    }

    private function newMySql(): MySql
    {
        $this->created++;
        $driver                     = new MySql([
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
            'options'               => [\PDO::ATTR_POOL_MAX => self::POOL_MAX],
        ]);
        $this->drivers[]            = $driver;

        return $driver->setStorageName('main');
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
}
