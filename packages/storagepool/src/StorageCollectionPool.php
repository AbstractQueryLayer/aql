<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageCollectionMutableInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class StorageCollectionPool implements StorageCollectionMutableInterface, AutoResolverInterface, StorageCollectionPoolInterface
{
    /**
     * @var array<string, string|StorageInterface|StoragePoolInterface>
     */
    protected array $storageCollection = [];

    protected ContainerInterface $diContainer;

    protected ContextVariablesInterface $contextVariables;

    /**
     * The decorator a pool lent for each borrowed original storage.
     *
     * @var \WeakMap<object, \WeakReference<object>>
     */
    private \WeakMap $lentDecorators;

    public function __construct()
    {
        $this->lentDecorators       = new \WeakMap();
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

    protected function defineStorageCollectionProxy(): StorageCollectionInterface
    {
        $proxy                      = $this->contextVariables->get(StorageCollectionPoolProxy::class);

        if ($proxy === null) {
            $proxy                  = new StorageCollectionPoolProxy($this);
            $this->contextVariables->set(StorageCollectionPoolProxy::class, $proxy);
        }

        return $proxy;
    }

    /**
     * @throws StorageException
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function borrowStorage(string $storageName): object|null
    {
        if (false === \array_key_exists($storageName, $this->storageCollection)) {
            throw new StorageException([
                'template'          => 'Storage {storageName} not found',
                'storageName'       => $storageName,
            ]);
        }

        $storage                    = $this->storageCollection[$storageName];

        if (\is_string($storage)) {
            $storage                = StorageCollection::instanciateStorage($storageName, $storage, $this->diContainer);
            $this->storageCollection[$storageName] = $storage;
        }

        if (false === $storage instanceof StoragePoolInterface) {
            return $storage;
        }

        $decorator                  = $storage->borrow();
        $originalObject             = $decorator instanceof DecoratorInterface ? $decorator->getOriginalObject() : null;

        if ($originalObject !== null) {
            $this->lentDecorators[$originalObject] = \WeakReference::create($decorator);
        }

        return $decorator;
    }

    /**
     * Gives a borrowed original storage back to its pool through the decorator the pool lent for it.
     */
    #[\Override]
    public function returnStorage(object $object): void
    {
        $decorator                  = $this->lentDecorators[$object]?->get();
        unset($this->lentDecorators[$object]);

        if ($decorator instanceof DisposableInterface) {
            $decorator->dispose();
        }
    }

    /**
     * Returns the decorator still lent for a borrowed original storage, so the storage stays borrowed from its pool.
     *
     * @throws StorageException
     */
    #[\Override]
    public function createStorageDecorator(object $originalObject): object
    {
        $decorator                  = $this->lentDecorators[$originalObject]?->get();

        if ($decorator === null) {
            throw new StorageException([
                'template'          => 'Storage {storage} was not borrowed from a pool',
                'storage'           => $originalObject::class,
            ]);
        }

        return $decorator;
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

    public function addStoragePool(string $storageName, StoragePoolInterface $storagePool): void
    {
        $this->storageCollection[$storageName] = $storagePool;
    }
}
