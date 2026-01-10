<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQueryInterface;
use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\Exceptions\DependencyNotFound;

class AqlExecutorDummy implements AqlExecutorInterface, AutoResolverInterface
{
    protected ContainerInterface $diContainer;

    protected ?StorageCollectionInterface $storageCollection = null;

    protected ?EntityFactoryInterface $entityFactory = null;

    /**
     * @throws DependencyNotFound
     */
    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;

        if ($this->storageCollection === null) {
            $this->storageCollection = $container->resolveDependency(StorageCollectionInterface::class);
        }

        if ($this->entityFactory === null) {
            $this->entityFactory    = $container->resolveDependency(EntityFactoryInterface::class);
        }
    }

    #[\Override]
    public function executeAql(
        BasicQueryInterface|PreprocessedQueryInterface            $query,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): ResultInterface|TupleInterface|InsertUpdateResultInterface {
        if ($this->storageCollection !== null && $this->entityFactory !== null) {
            $entity                 = $this->entityFactory->getEntity($this->defineEntityByQuery($query));
            $storage                = $this->storageCollection->findStorage($entity->getStorageName());

            $queryExecutor          = null;

            if ($storage instanceof QueryExecutorResolverInterface) {
                $queryExecutor      = $storage->resolveQueryExecutor($query, $entity);
            }

            if ($queryExecutor !== null) {

                if ($queryExecutor instanceof AutoResolverInterface) {
                    $queryExecutor->resolveDependencies($this->diContainer);
                }

                return $queryExecutor->executeQuery($query, $executionContext);
            }
        }

        return $this->newSqlQueryExecutor()->executeQuery($query, $executionContext);
    }

    #[\Override]
    public function preprocessingQuery(
        BasicQueryInterface $query,
        ?ExecutionContextInterface $executionContext = null
    ): void {
        if ($this->storageCollection !== null && $this->entityFactory !== null) {
            $entity                 = $this->entityFactory->getEntity($this->defineEntityByQuery($query));
            $storage                = $this->storageCollection->findStorage($entity->getStorageName());

            $queryExecutor          = null;

            if ($storage instanceof QueryExecutorResolverInterface) {
                $queryExecutor      = $storage->resolveQueryExecutor($query, $entity);
            }

            if ($queryExecutor !== null) {

                if ($queryExecutor instanceof AutoResolverInterface) {
                    $queryExecutor->resolveDependencies($this->diContainer);
                }

                $queryExecutor->preprocessing($query, $executionContext);
            }
        }

        $this->newSqlQueryExecutor()->preprocessing($query, $executionContext);
    }

    protected function defineEntityByQuery(BasicQueryInterface $query): ?string
    {
        $entityName                 = $query->getMainEntityName();

        if ($entityName !== '') {
            return $entityName;
        }

        if ($query instanceof QueryInterface) {
            return $query->getFrom()?->getSubject()->getSubjectName();
        }

        return null;
    }

    protected function newSqlQueryExecutor(): QueryExecutorInterface
    {
        $executor                   = new SqlQueryExecutor();
        $executor->resolveDependencies($this->diContainer);

        return $executor;
    }
}
