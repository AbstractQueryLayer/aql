<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DesignPatterns\Pool\PoolInterface;
use IfCastle\DI\DisposableInterface;

/**
 * The handle a storage pool lends: it holds the borrowed storage and gives it back to the pool when disposed.
 *
 * @implements DecoratorInterface<object>
 */
final class StorageDecorator implements DecoratorInterface, DisposableInterface
{
    /**
     * @var \WeakReference<PoolInterface<object>>|null
     */
    private \WeakReference|null $pool;

    /**
     * @param PoolInterface<object> $pool
     */
    public function __construct(private object|null $originalObject, PoolInterface $pool)
    {
        $this->pool                 = \WeakReference::create($pool);
    }

    public function __destruct()
    {
        $this->dispose();
    }

    #[\Override]
    public function getOriginalObject(): object|null
    {
        return $this->originalObject;
    }

    /**
     * Gives the storage back to the pool.
     */
    #[\Override]
    public function dispose(): void
    {
        if ($this->originalObject === null) {
            return;
        }

        $pool                       = $this->pool?->get();
        $this->pool                 = null;

        // The pool takes the original object from the decorator it lent and disposes the decorator again.
        $pool?->return($this);
        $this->originalObject       = null;
    }

    /**
     * Frees the pool slot without giving the storage back, and closes the storage: for a storage left in a state the
     * next borrower must not see, such as a transaction that failed to roll back.
     */
    public function discard(): void
    {
        $storage                    = $this->originalObject;

        if ($storage === null) {
            return;
        }

        $this->originalObject       = null;
        $pool                       = $this->pool?->get();
        $this->pool                 = null;

        // Without an original object the pool only forgets the decorator.
        $pool?->return($this);

        if ($storage instanceof DisposableInterface) {
            $storage->dispose();
        } elseif ($storage instanceof StorageInterface) {
            $storage->disconnect();
        }
    }
}
