<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Ddl;

use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\RuntimeException;
use IfCastle\Exceptions\UnexpectedValueType;

final class DdlStrategy implements AutoResolverInterface
{
    public static function instantiate(ContainerInterface $locator): self
    {
        $self                       = new self();
        $self->resolveDependencies($locator);

        return $self;
    }

    protected ContainerInterface $diContainer;

    protected EntityFactoryInterface $entityFactory;

    protected StorageCollectionInterface $storageCollection;

    protected bool $isRemoveExisted = false;

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
        $this->entityFactory        = $container->resolveDependency(EntityFactoryInterface::class);
        $this->storageCollection    = $container->resolveDependency(StorageCollectionInterface::class);
    }

    public function isRemoveExisted(): bool
    {
        return $this->isRemoveExisted;
    }

    /**
     * Allow removing table if it exists.
     *
     * @return $this
     */
    public function asRemoveExisted(): self
    {
        $this->isRemoveExisted      = true;

        return $this;
    }

    /**
     * @throws UnexpectedValueType
     * @throws RuntimeException
     */
    public function defineEntity(string $entityName): bool
    {
        $entity                     = $this->entityFactory->getEntity($entityName);
        $storage                    = $this->storageCollection->findStorage($entity->getStorageName());

        if ($storage === null) {
            throw new RuntimeException([
                'template'          => 'Storage for entity {entity} is not found',
                'entity'            => $entityName,
            ]);
        }

        $executor                   = null;

        if ($entity instanceof DdlExecutorAwareInterface) {
            $executor               = $entity->getDdlExecutor();
        }

        if ($executor === null && $storage instanceof DdlExecutorFactoryInterface) {
            $executor               = $storage->newDdlExecutor($entityName);
        }

        if ($executor === null) {
            throw new RuntimeException([
                'template'          => 'DdlExecutor for entity {entity} is not found',
                'entity'            => $entityName,
            ]);
        }

        if ($executor instanceof AutoResolverInterface) {
            $executor->resolveDependencies($this->diContainer);
        }

        if ($this->isRemoveExisted) {
            $executor->asRemoveExisted();
        }

        return $executor->executeDdl();
    }
}
