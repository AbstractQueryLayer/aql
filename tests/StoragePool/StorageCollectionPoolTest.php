<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DI\Container;
use IfCastle\DI\DisposableInterface;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

class StorageCollectionPoolTest extends TestCase
{
    private StorageCollectionPool $collection;

    private int $created            = 0;

    #[\Override]
    protected function setUp(): void
    {
        $contextVariables           = new class implements ContextVariablesInterface {
            private array $variables = [];

            #[\Override]
            public function get(string $key): mixed
            {
                return $this->variables[$key] ?? null;
            }

            #[\Override]
            public function set(string $key, mixed $value): void
            {
                $this->variables[$key] = $value;
            }
        };

        $this->collection           = new StorageCollectionPool();
        $this->collection->resolveDependencies(
            new Container(new Resolver(), [ContextVariablesInterface::class => $contextVariables])
        );
    }

    public function testSequentialBorrowsFromPoolShareDriver(): void
    {
        $this->collection->addStoragePool('main', new StoragePool($this->driverFactory(), 2));

        $first                      = $this->collection->borrowStorage('main');
        $this->assertInstanceOf(DecoratorInterface::class, $first);
        $driver                     = $first->getOriginalObject();
        $this->assertInstanceOf(DisposableInterface::class, $first);
        $first->dispose();

        $second                     = $this->collection->borrowStorage('main');

        $this->assertSame($driver, $second->getOriginalObject());
        $this->assertSame(1, $this->created);
    }

    public function testRegisteredStorageClassIsInstantiatedOnce(): void
    {
        $this->collection->registerStorage('main', NamelessStorage::class);

        $this->assertSame($this->collection->borrowStorage('main'), $this->collection->borrowStorage('main'));
    }

    /** A storage whose transaction failed to roll back is closed and never lent again, though the pool keeps one idle. */
    public function testStorageWhoseRollbackFailsIsDroppedFromItsPool(): void
    {
        $pool                       = new StoragePool($this->driverFactory(), 2);
        $this->collection->addStoragePool('main', $pool);

        $driver                     = $this->collection->findStorage('main');
        $this->assertInstanceOf(TransactionalRecordingStorage::class, $driver);
        $driver->transaction        = new Transaction(static fn() => throw new \RuntimeException('rollback failed'));

        try {
            $this->collection->releaseStorages();
            $this->fail('A failed rollback must be reported');
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback failed', $exception->getMessage());
        }

        $this->assertSame(0, $pool->getUsed());
        $this->assertTrue($driver->disposed);
        $this->assertNotSame($driver, $this->collection->findStorage('main'));
        $this->assertSame(2, $this->created);
    }

    /** Every context and every collection over the pool share the one self-pooling storage the pool built. */
    public function testSelfPoolingStorageIsBuiltOnceAndShared(): void
    {
        $contexts                   = new SwitchableContextVariables();
        $pool                       = new StoragePool($this->selfPoolingFactory(), 1);
        $first                      = $this->newCollection($contexts);
        $second                     = $this->newCollection($contexts);
        $first->addStoragePool('main', $pool);
        $second->addStoragePool('main', $pool);

        $storage                    = $first->findStorage('main');
        $this->assertInstanceOf(SelfPoolingRecordingStorage::class, $storage);

        for ($context = 0; $context < 3; $context++) {
            $contexts->startNewContext();
            $this->assertSame($storage, $first->findStorage('main'));
            $this->assertSame($storage, $second->findStorage('main'));
        }

        $this->assertSame(1, $this->created);
        $this->assertSame(1, $pool->getUsed());
    }

    /** Each context borrows its own storage from a pool whose storages have no connection pool of their own. */
    public function testStorageWithoutOwnPoolIsBorrowedPerContext(): void
    {
        $contexts                   = new SwitchableContextVariables();
        $pool                       = new StoragePool($this->driverFactory(), 2);
        $collection                 = $this->newCollection($contexts);
        $collection->addStoragePool('main', $pool);

        $first                      = $collection->borrowStorage('main');
        $second                     = $collection->borrowStorage('main');

        $this->assertInstanceOf(DecoratorInterface::class, $first);
        $this->assertInstanceOf(DecoratorInterface::class, $second);
        $this->assertNotSame($first->getOriginalObject(), $second->getOriginalObject());
        $this->assertSame(2, $this->created);
        $this->assertSame(2, $pool->getUsed());
    }

    private function newCollection(SwitchableContextVariables $contexts): StorageCollectionPool
    {
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies(
            new Container(new Resolver(), [ContextVariablesInterface::class => $contexts])
        );

        return $collection;
    }

    private function selfPoolingFactory(): FactoryInterface
    {
        return new class ($this->created) implements FactoryInterface {
            public function __construct(private int &$created) {}

            #[\Override]
            public function createObject(): object
            {
                return (new SelfPoolingRecordingStorage('shared' . ++$this->created))->setStorageName('main');
            }
        };
    }

    private function driverFactory(): FactoryInterface
    {
        return new class ($this->created) implements FactoryInterface {
            public function __construct(private int &$created) {}

            #[\Override]
            public function createObject(): object
            {
                return (new TransactionalRecordingStorage('driver' . ++$this->created))->setStorageName('main');
            }
        };
    }
}
