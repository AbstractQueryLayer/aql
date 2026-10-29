<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DI\Container;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/**
 * A StorageCollectionPool over coroutine context variables: each coroutine holds storages of its own, and they go back
 * to the pool when the coroutine ends, not when the context is freed.
 */
final class CoroutineContextVariablesTest extends TestCase
{
    // Milliseconds each child holds its storage, so that the two children hold theirs at once.
    private const int OVERLAP_MS    = 20;

    private int $created            = 0;

    public function testChildCoroutinesHoldStoragesOfTheirOwnUntilTheParentEnds(): void
    {
        $pool                       = new StoragePool($this->driverFactory(), 3);
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies(
            new Container(new Resolver(), [ContextVariablesInterface::class => new CoroutineContextVariables()])
        );
        $collection->addStoragePool('main', $pool);

        // The coroutines are kept, so their contexts are not freed and no destructor gives a storage back.
        $children                   = [];
        $parent                     = spawn(function () use ($collection, &$children): array {
            $own                    = $collection->findStorage('main');

            for ($child = 0; $child < 2; $child++) {
                $children[]         = spawn(static function () use ($collection): object {
                    $storage        = $collection->findStorage('main');
                    delay(self::OVERLAP_MS);

                    return $storage;
                });
            }

            return [$own, ...\array_map(static fn($child): object => await($child), $children)];
        });

        $storages                   = await($parent);

        $this->assertCount(3, \array_unique(\array_map(spl_object_id(...), $storages)), 'a storage per coroutine');
        $this->assertSame(3, $this->created);
        $this->assertTrue($parent->isCompleted());
        $this->assertSame(0, $pool->getUsed(), 'the storages are back when the coroutines end');
    }

    /**
     * A coroutine that ends with a transaction open has it rolled back and its storage given back; the warning about
     * it, thrown by an error handler, does not escape the completion callback.
     */
    public function testATransactionLeftOpenIsRolledBackAtTheEnd(): void
    {
        $pool                       = new StoragePool($this->driverFactory(), 1);
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies(
            new Container(new Resolver(), [ContextVariablesInterface::class => new CoroutineContextVariables()])
        );
        $collection->addStoragePool('main', $pool);
        $rolledBack                 = false;

        $coroutine                  = spawn(static function () use ($collection, &$rolledBack): void {
            $storage                = $collection->findStorage('main');
            $storage->transaction   = new Transaction(static function () use ($storage, &$rolledBack): void {
                $storage->transaction = null;
                $rolledBack         = true;
            });
        });

        $errorLog                   = \ini_set('error_log', '/dev/null');
        \set_error_handler(static fn(int $level, string $message) => throw new \ErrorException($message, 0, $level));

        try {
            await($coroutine);
        } finally {
            \restore_error_handler();
            \ini_set('error_log', (string) $errorLog);
        }

        $this->assertTrue($rolledBack);
        $this->assertSame(0, $pool->getUsed());
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
