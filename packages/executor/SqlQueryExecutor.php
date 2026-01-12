<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Relation\RelationDirection;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Helpers\ContextHelper;

/**
 * Executive and SQL query converter.
 *
 */
class SqlQueryExecutor extends QueryExecutorAbstract implements
    QueryHandlerInterface,
    QueryPostHandlerInterface,
    ColumnHandlerInterface,
    FunctionHandlerInterface,
    OptionHandlerInterface,
    SubjectHandlerInterface
{
    #[\Override]
    protected function handleSubquery(SubqueryInterface $query, NodeContextInterface $context): void
    {
        $contextName                = ContextHelper::resolveContextName($query->getParentNode());

        // No need to resolve relations for Derived Tables.
        // Derived Tables are subqueries in the JOIN clause or CTE (Common Table Expression)
        if ($contextName === NodeContextInterface::CONTEXT_JOIN || $contextName === NodeContextInterface::CONTEXT_CTE) {
            return;
        }

        //
        // Dependency direction. For Tuple is FROM_RIGHT i.e., from the main entity to subquery entity.
        //
        $direction                  = match ($contextName) {
            NodeContextInterface::CONTEXT_TUPLE => RelationDirection::FROM_RIGHT,
            default                             => RelationDirection::FROM_LEFT
        };

        $leftEntity                 = $context->getEntity($query->getMainEntityName());
        $rightEntity                = $context->getParentContext()?->getCurrentEntity() ?? throw new TransformationException([
            'template'              => 'Expected parent context to be set for subquery {aql}',
            'aql'                   => $query->getAql(),
        ]);

        $relation                   = $rightEntity->resolveRelation($leftEntity);

        if ($contextName === NodeContextInterface::CONTEXT_TUPLE) {
            $query->returnOnlyOne();
        }

        if ($direction === RelationDirection::FROM_RIGHT) {
            $query->getWhere()->add($relation->generateConditions());
        }
    }
}
