<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SelfPoolingStorageInterface;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageCollectionMutableInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\CompositeException;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * A storage collection whose storages come from pools. Each execution context holds the storages it found until the
 * context ends or releaseStorages() is called, so the context variables must belong to one coroutine and must not be
 * inherited by the coroutines it starts: two coroutines sharing them would run statements on one connection at once.
 * CoroutineContextVariables are such variables for TrueAsync.
 * A storage that pools its own connections is the exception: every context gets the one instance (addStoragePool()).
 */
class StorageCollectionPool implements StorageCollectionMutableInterface, AutoResolverInterface, StorageCollectionPoolInterface
{
    /**
     * @var array<string, string|StorageInterface|StoragePoolInterface>
     */
    protected array $storageCollection = [];

    /**
     * The self-pooling storage each pool lent for good, held by the handle the pool lent it with: holding the handle
     * keeps the storage counted as borrowed, so the pool never builds a second one, and every collection over the
     * pool shares it.
     *
     * @var \WeakMap<StoragePoolInterface, object>|null
     */
    private static ?\WeakMap $sharedLoans = null;

    protected ContainerInterface $diContainer;

    protected ContextVariablesInterface $contextVariables;

    private static int $instances   = 0;

    /**
     * The context variable holding this collection's proxy: one per collection, so two collections in one context do
     * not take storages from each other's pools.
     */
    private readonly string $contextKey;

    public function __construct()
    {
        $this->contextKey           = StorageCollectionPoolProxy::class . '#' . ++self::$instances;
    }

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
        $this->contextVariables     = $container->resolveDependency(ContextVariablesInterface::class);
    }

    #[\Override]
    public function findStorage(?string $storageName = null): ?StorageInterface
    {
        return $this->defineStorageCollectionProxy()->findStorage($storageName);
    }

    /**
     * Gives the storages the current context holds back to their pools, for a context that outlives one unit of work,
     * such as a worker between jobs. A transaction left open is rolled back and reported.
     *
     * @throws StorageException|CompositeException after all storages are released, when a transaction was open
     */
    public function releaseStorages(): void
    {
        $proxy                      = $this->contextVariables->get($this->contextKey);

        if ($proxy instanceof StorageCollectionPoolProxy) {
            $proxy->release();
        }
    }

    protected function defineStorageCollectionProxy(): StorageCollectionInterface
    {
        $proxy                      = $this->contextVariables->get($this->contextKey);

        if ($proxy === null) {
            $proxy                  = new StorageCollectionPoolProxy($this);
            $this->contextVariables->set($this->contextKey, $proxy);
            // Given back when the context ends, not when its variables are freed: a destructor runs, with the rollback
            // of a transaction left open, at a point nobody controls.
            $this->contextVariables->defer($proxy->releaseAtEnd(...));
        }

        return $proxy;
    }

    /**
     * @return object|null the storage, the decorator its pool lent for it, or null for an unknown name
     * @throws StorageException when the pool of the storage has no free storage
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function borrowStorage(string $storageName): object|null
    {
        if (false === \array_key_exists($storageName, $this->storageCollection)) {
            return null;
        }

        $storage                    = $this->storageCollection[$storageName];

        if (\is_string($storage)) {
            $storage                = StorageCollection::instanciateStorage($storageName, $storage, $this->diContainer);
            $this->storageCollection[$storageName] = $storage;
        }

        if (false === $storage instanceof StoragePoolInterface) {
            return $storage;
        }

        return $storage->borrow() ?? throw new StorageException([
            'template'              => 'The pool of the storage {storageName} has no free storage: {used} of {max} '
                                       . 'are borrowed',
            'storageName'           => $storageName,
            'used'                  => $storage->getUsed(),
            'max'                   => $storage->getMaxPoolSize(),
        ]);
    }

    #[\Override]
    public function registerStorage(string $storageName, string $storageClass): void
    {
        $this->storageCollection[$storageName] = $storageClass;
    }

    #[\Override]
    public function addStorage(string $storageName, StorageInterface $storage): void
    {
        $this->storageCollection[$storageName] = $storage;
    }

    /**
     * Adds a pool of storages. The pool lends its first storage here: a storage that pools its own connections serves
     * every context as one shared instance, so the collection keeps it and never borrows from the pool again; pooling
     * it again would open a native pool per instance. Any other storage goes back, and each context borrows its own.
     * A pool with no free storage at this moment is kept as is, and its storages are borrowed per context.
     *
     * @throws UnexpectedValueType when the pool lends something other than a storage
     */
    public function addStoragePool(string $storageName, StoragePoolInterface $storagePool): void
    {
        self::$sharedLoans          ??= new \WeakMap();
        $loan                       = self::$sharedLoans[$storagePool] ?? $storagePool->borrow();

        if ($loan === null) {
            $this->storageCollection[$storageName] = $storagePool;
            return;
        }

        $storage                    = $loan instanceof DecoratorInterface ? $loan->getOriginalObject() : $loan;

        if ($storage instanceof SelfPoolingStorageInterface) {
            self::$sharedLoans[$storagePool] = $loan;
            $this->storageCollection[$storageName] = $storage;
            return;
        }

        if ($loan instanceof DisposableInterface) {
            $loan->dispose();
        }

        if (false === $storage instanceof StorageInterface) {
            throw new UnexpectedValueType('storage.' . $storageName, $storage, StorageInterface::class);
        }

        $this->storageCollection[$storageName] = $storagePool;
    }
}
