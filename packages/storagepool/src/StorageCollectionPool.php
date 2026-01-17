<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageCollectionMutableInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class StorageCollectionPool implements StorageCollectionMutableInterface, AutoResolverInterface, StorageCollectionPoolInterface
{
    /**
     * @var array<string, string|StorageInterface|StoragePoolInterface>
     */
    protected array $storageCollection = [];

    protected ContainerInterface $diContainer;

    protected ContextVariablesInterface $contextVariables;

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
        }

        if ($storage instanceof StoragePoolInterface) {
            return $storage->borrow();
        }

        return $storage;
    }

    #[\Override]
    public function returnStorage(object $object): void {}

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
}
