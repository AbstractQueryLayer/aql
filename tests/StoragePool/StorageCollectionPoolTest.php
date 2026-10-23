<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\StoragePool\ReadWritePool\RecordingSqlStorage;
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

    public function testReturnStorageGivesDriverBackThroughLentDecorator(): void
    {
        $pool                       = new StoragePool($this->driverFactory(), 2);
        $this->collection->addStoragePool('main', $pool);

        $decorator                  = $this->collection->borrowStorage('main');
        $driver                     = $decorator->getOriginalObject();

        $this->assertSame($decorator, $this->collection->createStorageDecorator($driver));

        $this->collection->returnStorage($driver);

        $this->assertSame(0, $pool->getUsed());
        $this->assertSame($driver, $this->collection->borrowStorage('main')->getOriginalObject());
    }

    public function testRegisteredStorageClassIsInstantiatedOnce(): void
    {
        $this->collection->registerStorage('main', NamelessStorage::class);

        $this->assertSame($this->collection->borrowStorage('main'), $this->collection->borrowStorage('main'));
    }

    private function driverFactory(): FactoryInterface
    {
        return new class ($this->created) implements FactoryInterface {
            public function __construct(private int &$created) {}

            #[\Override]
            public function createObject(): object
            {
                return (new RecordingSqlStorage('driver' . ++$this->created))->setStorageName('main');
            }
        };
    }
}
