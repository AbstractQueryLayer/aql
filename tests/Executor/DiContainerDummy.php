<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

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
use IfCastle\AQL\Storage\SqlStorageMock;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\Resolver;

class DiContainerDummy
{
    public static function build(array $data = [], ?ContainerInterface $parent = null): ContainerInterface
    {
        $builder                    = new ContainerBuilder();

        $builder->bindConstructible(EntityBuilderInterface::class, EntityBuilder::class)
                ->bindConstructible(EntityAspectBuilderFactoryInterface::class, EntityAspectBuilderFactory::class)
                ->bindConstructible(
                    [EntityFactoryInterface::class, EntityDescriptorFactoryInterface::class, EntityStorageInterface::class],
                    EntityMemoryFactory::class
                )

                ->bindConstructible(NamingStrategyInterface::class, SnakeTableCamelFieldNaming::class)
                ->bindConstructible(StorageCollectionInterface::class, StorageCollection::class)
                ->bindInjectable(AqlExecutorInterface::class, AqlExecutorDummy::class)
                ->bindConstructible(StorageInterface::class, SqlStorageMock::class);

        foreach ($data as $key => $value) {
            $builder->set($key, $value);
        }

        return $builder->buildContainer(new Resolver(), $parent);
    }
}
