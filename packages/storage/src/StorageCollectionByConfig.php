<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\ConfigurableFromArrayInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class StorageCollectionByConfig implements
    StorageCollectionInterface,
    AutoResolverInterface,
    DisposableInterface
{
    protected ContainerInterface $diContainer;

    protected array $config;

    /**
     * @var array<string, StorageInterface>
     */
    protected array $storageList    = [];

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
        $configRegistry             = $container->resolveDependency(ConfigInterface::class);

        $config                     = $configRegistry->findSection('storages');

        if ($config === null) {
            $config                 = ['main' => $configRegistry->requireSection('database')];
        }

        $this->config               = $config;

        // Every storage is built with the collection, so one that opens a connection pool opens it here
        // rather than inside the first request that asks for it; applications resolve the collection
        // at startup.
        foreach ($config as $storageName => $storageConfig) {
            $storageClass           = $storageConfig['class'] ?? throw new StorageException([
                'template'          => 'Config key class is required for storage {storageName}',
                'storageName'        => $storageName,
            ]);

            $this->storageList[$storageName] = $this->buildStorage($storageName, $storageClass);
        }
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

    #[\Override]
    public function findStorage(?string $storageName = null): ?StorageInterface
    {
        return $this->storageList[$storageName ?? StorageCollectionInterface::STORAGE_MAIN] ?? null;
    }

    /**
     * @throws UnexpectedValueType
     */
    private function buildStorage(string $storageName, string $storageClass): StorageInterface
    {
        $storage                    = StorageCollection::instanciateStorage(
            $storageName, $storageClass, $this->diContainer, $this->config[$storageName]
        );

        if ($storage instanceof ConfigurableFromArrayInterface) {
            $storage->configureFromArray($this->config[$storageName]);
        }

        return $storage;
    }
}
