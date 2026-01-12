<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class StorageCollection implements
    StorageCollectionMutableInterface,
    AutoResolverInterface,
    DisposableInterface
{
    /**
     * @throws UnexpectedValueType
     */
    public static function instanciateStorage(string $storageName, string $storageClass, ContainerInterface $container): StorageInterface
    {
        $storage                = new $storageClass();

        if ($storage instanceof AutoResolverInterface) {
            $storage->resolveDependencies($container);
        }

        if ($storage instanceof StorageInterface) {
            $storage->setStorageName($storageName);
        } else {
            throw new UnexpectedValueType('storage.' . $storageName, $storage, StorageInterface::class);
        }

        return $storage;
    }

    protected ContainerInterface $diContainer;

    public function __construct(
        /**
         * @var array<string, string|StorageInterface>
         */
        protected array $storageList = []
    ) {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
    }

    /**
     *
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function findStorage(?string $storageName = null): ?StorageInterface
    {
        $storageName                ??= self::STORAGE_MAIN;

        if (false === \array_key_exists($storageName, $this->storageList)) {
            return null;
        }

        $storage                    = $this->storageList[$storageName];

        if (\is_string($storage)) {
            $storage                = self::instanciateStorage($storageName, $storage, $this->diContainer);
            $this->storageList[$storageName] = $storage;
        }

        return $storage;
    }

    #[\Override]
    public function registerStorage(string $storageName, string $storageClass): void
    {
        $this->storageList[$storageName] = $storageClass;
    }

    #[\Override]
    public function addStorage(string $storageName, StorageInterface $storage): void
    {
        $this->storageList[$storageName] = $storage;
    }

    #[\Override]
    public function dispose(): void
    {
        $storageList                = $this->storageList;
        $this->storageList          = [];

        foreach ($storageList as $storage) {
            if ($storage instanceof DisposableInterface) {
                $storage->dispose();
            }
        }
    }
}
