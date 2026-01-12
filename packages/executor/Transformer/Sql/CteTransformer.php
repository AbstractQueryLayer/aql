<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Transformer\Sql;

use IfCastle\AQL\Dsl\Sql\Query\DeleteInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Dsl\Sql\Query\UpdateInterface;
use IfCastle\AQL\Dsl\Sql\Query\WithInterface;
use IfCastle\AQL\Entity\DerivedEntity\AutoAddingPropertyResolver;
use IfCastle\AQL\Entity\DerivedEntity\DerivedEntity;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\DI\Exceptions\DependencyNotFound;

class CteTransformer
{
    protected readonly mixed $queryTransformer;

    public function __construct(callable $queryTransformer)
    {
        $this->queryTransformer     = $queryTransformer;
    }

    /**
     * @throws QueryException
     * @throws DependencyNotFound
     * @throws TransformationException
     */
    public function __invoke(WithInterface $cte, NodeContextInterface $context): void
    {
        // Main context for the query which contains all derived entities
        $mainContext                = $context->newNodeContext($cte, NodeContextInterface::CONTEXT_CTE)
                                              ->asDerivedEntityStorage();

        $context->addToInherited($mainContext);

        // First, we need to handle the main query for understanding which columns are required.
        if ($cte->getQuery() === null) {
            throw new TransformationException([
                'template'          => 'Main Query is not defined for CTE',
                'aql'               => $cte->getAql(),
            ]);
        }

        $query                      = $cte->getQuery();
        $queryContext               = $mainContext->newNodeContext($query, NodeContextInterface::CONTEXT_QUERY);

        match (true) {
            $query->isSelect() && $query instanceof SelectInterface => $this->preHandleSelect($query, $queryContext),
            $query->isInsert()      => throw new QueryException([
                'query'             => $this,
                'message'           => 'Insert operation is not allowed for CTE',
            ]),
            $query->isUpdate() && $query instanceof UpdateInterface => $this->preHandleUpdate($query, $queryContext),
            $query->isDelete() && $query instanceof DeleteInterface => $this->preHandleDelete($query, $queryContext),
            default                 => throw new QueryException([
                'query'             => $this,
                'message'           => 'Unsupported CTE target query type: ' . $query::class,
            ]),
        };

        $entityFactory              = $context->resolveDependency(EntityFactoryInterface::class);

        $storage                    = null;
        $defaultStorage             = $cte->getQueryStorage() ?? StorageCollectionInterface::STORAGE_MAIN;

        foreach ($cte->getSubqueries() as $subquery) {

            $originalEntityName     = $subquery->searchDerivedEntity();
            $originalEntity         = $context->getEntity($originalEntityName);
            $queryStorage           = $originalEntity->getStorageName() ?? $defaultStorage;

            if ($storage === null) {
                $storage             = $queryStorage;
            }

            if ($storage !== $queryStorage) {
                throw new TransformationException([
                    'template'      => 'CTE subqueries must have the same storage {storage} and {subqueryStorage}. '
                                       . 'Subquery alias: {subqueryAlias}. CTE: {aql}',
                    'storage'       => $storage,
                    'subqueryStorage' => $queryStorage,
                    'aql'           => $cte->getAql(),
                    'subqueryAlias' => $subquery->getCteAlias(),
                ]);
            }

            $entity                 = new DerivedEntity(
                $subquery,
                new Subject($originalEntityName, subjectAlias: $subquery->getCteAlias()),
                $entityFactory,
                new AutoAddingPropertyResolver($subquery, $originalEntity, $entityFactory)
            );

            // If someone tries to get properties from this entity, we need to resolve them
            $entity->setResolvePropertiesMode(true);
            $mainContext->addDerivedEntity($entity);
        }

        if ($cte->getQueryStorage() === null) {
            $cte->setQueryStorage($storage);
        }

        if ($cte->getSubstitution() !== null) {
            return;
        }

        // First, we need to handle the main query for understanding which columns are required.
        // Then we need to handle the CTE expressions.
        $query->transformWith($this->queryTransformer, $queryContext);

        foreach ($cte->getSubqueries() as $subquery) {
            $subqueryContext        = $mainContext->newNodeContext($subquery, NodeContextInterface::CONTEXT_QUERY)
                                                 ->notInheritAliases();

            $mainContext->addToInherited($subqueryContext);
            $subquery->transformWith($this->queryTransformer, $subqueryContext);
        }
    }

    protected function preHandleSelect(SelectInterface $select, NodeContextInterface $context): void {}

    protected function preHandleUpdate(UpdateInterface $update, NodeContextInterface $context): void {}

    protected function preHandleDelete(DeleteInterface $delete, NodeContextInterface $context): void {}
}
