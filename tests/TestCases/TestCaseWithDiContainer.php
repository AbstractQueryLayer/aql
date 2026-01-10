<?php

declare(strict_types=1);

namespace IfCastle\AQL\TestCases;

use IfCastle\AQL\Entity\Builder\EntityAspectBuilderFactory;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderFactoryInterface;
use IfCastle\AQL\Entity\Builder\EntityBuilder;
use IfCastle\AQL\Entity\Builder\EntityBuilderInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\SnakeTableCamelFieldNaming;
use IfCastle\AQL\Entity\Manager\EntityDescriptorFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityMemoryFactory;
use IfCastle\AQL\Entity\Manager\EntityStorageInterface;
use IfCastle\AQL\Executor\AqlExecutorDummy;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Entities\Book;
use IfCastle\AQL\Generator\Ddl\EntityToTableFactoryByStorages;
use IfCastle\AQL\Generator\Ddl\EntityToTableFactoryInterface;
use IfCastle\AQL\Storage\SomeStorageMock;
use IfCastle\AQL\Storage\SqlStorageMock;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageCollectionMutableInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DI\ComponentRegistryInMemory;
use IfCastle\DI\ComponentRegistryInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\DI\ConfigMutableInterface;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

abstract class TestCaseWithDiContainer extends TestCase
{
    protected ?ContainerInterface $diContainer = null;

    protected function getDiContainer(): ?ContainerInterface
    {
        return $this->diContainer;
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->defineContainer();
    }

    private function defineContainer(): void
    {
        $containerBuilder           = new ContainerBuilder(resolveScalarAsConfig: false);
        $containerBuilder->bindSelfReference();

        $this->buildDiContainer($containerBuilder);

        $this->diContainer          = $containerBuilder->buildContainer(new Resolver());
    }

    protected static function buildDiContainerDefaults(ContainerBuilder $builder): void
    {
        $builder->bindConstructible(EntityBuilderInterface::class, EntityBuilder::class)
                ->bindConstructible(EntityAspectBuilderFactoryInterface::class, EntityAspectBuilderFactory::class)
                ->bindConstructible(
                    [EntityFactoryInterface::class, EntityDescriptorFactoryInterface::class, EntityStorageInterface::class],
                    EntityMemoryFactory::class
                )
                ->bindConstructible(NamingStrategyInterface::class, SnakeTableCamelFieldNaming::class)
                ->bindInjectable(AqlExecutorInterface::class, AqlExecutorDummy::class)
                ->bindConstructible(StorageInterface::class, SqlStorageMock::class)
                ->bindConstructible(EntityToTableFactoryInterface::class, EntityToTableFactoryByStorages::class)
                ->bindObject(ConfigInterface::class, new ConfigMutable());

        $builder->bindInitializer(StorageCollectionInterface::class, static::defineStorageCollection(...));

        $builder->set('entityNamespaces', [Book::getBaseDir() => Book::namespace()]);

        // Create a new registry and add the component configuration
        $registry                   = new ComponentRegistryInMemory();
        $registry->addComponentConfig(EntityAspectBuilderFactoryInterface::class, new ConfigMutable([
            'namespaces'            => ['IfCastle\\AQL\\Aspects'],
        ]));

        $builder->bindObject(ComponentRegistryInterface::class, $registry);
    }

    protected static function defineStorageCollection(ContainerInterface $container): StorageCollectionInterface
    {
        $storageCollection          = new StorageCollection();
        $storageCollection->resolveDependencies($container);

        static::defineMainStorage($storageCollection);
        static::defineSecondaryStorages($storageCollection);

        return $storageCollection;
    }

    protected static function defineMainStorage(StorageCollectionMutableInterface $storageCollection): void
    {
        $storageCollection->registerStorage(StorageCollectionInterface::STORAGE_MAIN, SqlStorageMock::class);
    }

    protected static function defineSecondaryStorages(StorageCollectionMutableInterface $storageCollection): void
    {
        $storageCollection->registerStorage(SomeStorageMock::NAME, SomeStorageMock::class);
    }

    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        self::buildDiContainerDefaults($containerBuilder);
    }

    protected function getMainStorage(): StorageInterface|null
    {
        return $this->diContainer->findDependency(StorageCollectionInterface::class)?->findStorage(StorageCollectionInterface::STORAGE_MAIN);
    }

    protected function getAqlExecutor(): AqlExecutorInterface
    {
        return $this->diContainer->resolveDependency(AqlExecutorInterface::class);
    }

    protected function getEntityFactory(): EntityFactoryInterface
    {
        return $this->diContainer->resolveDependency(EntityFactoryInterface::class);
    }

    protected function getConfigMutable(): ConfigMutableInterface
    {
        return $this->diContainer->resolveDependency(ConfigInterface::class);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->diContainer instanceof DisposableInterface) {
            $this->diContainer->dispose();
        }

        $this->diContainer = null;
    }
}
