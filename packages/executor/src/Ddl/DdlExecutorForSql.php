<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Ddl;

use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableAwareInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableFactoryInterface;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SqlStorageInterface;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\RuntimeException;

class DdlExecutorForSql implements DdlExecutorInterface, AutoResolverInterface
{
    protected ContainerInterface $diContainer;

    protected EntityFactoryInterface $entityFactory;

    protected StorageCollectionInterface $storageCollection;

    protected ?EntityToTableFactoryInterface $entityToTableFactory = null;

    protected bool $isRemoveExisted = false;

    public function __construct(protected string $entityName) {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container->resolveDependency(ContainerInterface::class);
        $this->entityFactory        = $container->resolveDependency(EntityFactoryInterface::class);
        $this->storageCollection    = $container->resolveDependency(StorageCollectionInterface::class);
        $this->entityToTableFactory = $container->resolveDependency(EntityToTableFactoryInterface::class);
    }

    #[\Override]
    public function isRemoveExisted(): bool
    {
        return $this->isRemoveExisted;
    }

    #[\Override]
    public function asRemoveExisted(): static
    {
        $this->isRemoveExisted      = true;

        return $this;
    }

    /**
     * @throws RecoverableException
     * @throws RuntimeException
     * @throws StorageException
     * @throws QueryException
     */
    #[\Override]
    public function executeDdl(): bool
    {
        $entity                     = $this->entityFactory->getEntity($this->entityName);
        $storage                    = $this->storageCollection->findStorage($entity->getStorageName());

        if ($storage === null) {
            throw new RuntimeException([
                'template'          => 'Storage for entity {entity} is not found',
                'entity'            => $this->entityName,
            ]);
        }

        $generator                  = null;

        if ($entity instanceof EntityToTableAwareInterface) {
            $generator              = $entity->getEntityToTableGenerator();
        }

        if ($generator === null && $storage instanceof EntityToTableFactoryInterface) {
            $generator              = $storage->newEntityToTableGenerator($entity);
        }

        if ($generator === null && $this->entityToTableFactory !== null) {
            $generator              = $this->entityToTableFactory->newEntityToTableGenerator($entity);
        }

        if ($generator === null) {
            return false;
        }

        if ($generator instanceof AutoResolverInterface) {
            $generator->resolveDependencies($this->diContainer);
        }

        $ddl                        = $generator->generate();

        if ($this->isRemoveExisted) {
            $this->dropTableIfExist($ddl->getTableName(), $storage);
        }

        $ddl                        = $ddl->getResultAsString();

        if (empty($ddl)) {
            return false;
        }

        if ($storage instanceof SqlStorageInterface) {
            $storage->executeSql($ddl);
        }

        return true;
    }

    protected function dropTableIfExist(string $tableName, SqlStorageInterface $storage): void
    {
        $storage->executeSql('DROP TABLE IF EXISTS ' . $storage->escape($tableName));
    }
}
