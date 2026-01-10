<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\ConfigurableFromArrayInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;

class StorageCollectionByConfig implements
    StorageCollectionInterface,
    AutoResolverInterface,
    DisposableInterface
{
    protected ContainerInterface $diContainer;

    protected array $config;

    /**
     * @var array<string, string|StorageInterface>
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

        foreach ($config as $storageName => $storageConfig) {
            $this->storageList[$storageName] = $storageConfig['class'] ?? throw new StorageException([
                'template'          => 'Config key class is required for storage {storageName}',
                'storageName'        => $storageName,
            ]);
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
        $storageName                ??= StorageCollectionInterface::STORAGE_MAIN;

        if (false === \array_key_exists($storageName, $this->storageList)) {
            return null;
        }

        $storageClass               = $this->storageList[$storageName];

        if ($storageClass instanceof StorageInterface) {
            return $storageClass;
        }

        $storage                    = StorageCollection::instanciateStorage($storageName, $storageClass, $this->diContainer);

        if ($storage instanceof ConfigurableFromArrayInterface) {
            $storage->configureFromArray($this->config[$storageName]);
        }

        $this->storageList[$storageName] = $storage;

        return $storage;
    }
}
