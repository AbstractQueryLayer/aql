<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\SqlQueryExecutor;

class SQLiteQueryExecutor extends SqlQueryExecutor
{
    #[\Override]
    public function postHandleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        $query                  = $query->resolveNode();

        if ($query instanceof QueryInterface && $query->isInsert()) {
            $node               = $query->getAssigmentList()?->resolveNode();

            if ($node instanceof AssignmentListInterface) {
                // SQLite does not support INSERT ... SET
                $node->asValueSyntax();
            }
        } elseif ($query instanceof QueryInterface && $query->isModifying()) {
            $this->refuseUnsupportedRowSelection($query, $context);
        } elseif ($query instanceof SelectInterface) {
            $this->refuseLockingRead($query);
        }

        parent::postHandleQuery($query, $context);
    }

    /**
     * SQLite has no row-level locks, so there are no rows for FOR UPDATE or LOCK IN SHARE MODE to lock: a locking
     * read would run as a plain read and promise a protection it does not give. It is refused.
     *
     * @throws QueryException
     */
    private function refuseLockingRead(SelectInterface $query): void
    {
        $lockMode                   = $query->getLockMode();

        if ($lockMode === null) {
            return;
        }

        throw new QueryException([
            'query'                 => $query,
            'clause'                => $lockMode->value,
            'template'              => 'SQLite has no row-level locks and does not support {clause} in {query};'
                                       . ' the statement is refused because it would run as a plain read',
        ]);
    }

    /**
     * SQLite takes no LIMIT or ORDER BY in UPDATE and DELETE, so the query runs without them. That is safe only when
     * the dropped clause selected nothing: `LIMIT 1` over a filter that already names one row by its primary key.
     * Any other LIMIT or ORDER BY would widen the statement to every matching row, so the query is refused.
     *
     * @throws QueryException
     */
    private function refuseUnsupportedRowSelection(QueryInterface $query, NodeContextInterface $context): void
    {
        $limit                      = $query->getLimit()?->resolveNode();

        $clause                     = match (true) {
            $limit?->isNotEmpty() === true && false === $this->isOneRowByPrimaryKey($query, $limit, $context)
                                    => 'LIMIT',
            $query->getOrderBy()?->resolveNode()->isNotEmpty() === true
                                    => 'ORDER BY',
            default                 => null,
        };

        if ($clause === null) {
            return;
        }

        throw new QueryException([
            'query'                 => $query,
            'clause'                => $clause,
            'template'              => 'SQLite does not support {clause} in {query}; the statement is refused'
                                       . ' because running it without the clause would change other rows',
        ]);
    }

    /**
     * Whether `LIMIT 1` is redundant: the WHERE is an AND of equalities between every primary key column of the
     * entity and a constant, so at most one row matches.
     */
    private function isOneRowByPrimaryKey(
        QueryInterface $query,
        LimitInterface $limit,
        NodeContextInterface $context
    ): bool
    {
        if ($limit instanceof Limit === false || $limit->getLimit() !== 1 || $limit->getOffset() !== 0) {
            return false;
        }

        $primaryKey                 = $context->getCurrentEntity()->getPrimaryKey();
        $where                      = $query->getWhere();

        if ($primaryKey === null || $where instanceof Conditions === false
            || $where->getType() !== Conditions::TYPE_AND) {
            return false;
        }

        $filtered                   = [];

        foreach ($where->getChildNodes() as $condition) {
            $column                 = $condition instanceof LROperationInterface
                                      && $condition->getOperation() === LROperationInterface::EQU
                                      && $condition->getRightNode() instanceof ConstantInterface
                ? $condition->getLeftNode()
                : null;

            if ($column instanceof ColumnInterface) {
                $filtered[$column->getColumnName()] = true;
            }
        }

        return \array_diff($primaryKey->getKeyColumns(), \array_keys($filtered)) === [];
    }

    #[\Override]
    protected function handleProperty(ColumnInterface $column, NodeContextInterface $context, EntityInterface $entity): void
    {
        parent::handleProperty($column, $context, $entity);

        $column                     = $column->resolveNode();

        // SQLite does not support UPDATE ... FROM aliases
        if ($context->getCurrentSqlQuery()->getResolvedAction() === QueryInterface::ACTION_UPDATE) {
            $column->setSubject('')->setSubjectAlias('');
        }
    }

    #[\Override]
    public function handleSubject(SubjectInterface $subject, NodeContextInterface $context): void
    {
        parent::handleSubject($subject, $context);

        $subject                    = $subject->resolveNode();

        if (!$subject instanceof SubjectInterface) {
            return;
        }

        // SQLite has no USE/FORCE/IGNORE INDEX; a hint changes only the plan, so the query keeps its rows without it
        $subject->clearIndexHints();

        // SQLite does not support UPDATE ... FROM aliases
        if ($context->getCurrentSqlQuery()->getResolvedAction() === QueryInterface::ACTION_UPDATE) {
            $subject->setSubjectAlias('');
        }
    }
}
