<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
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
            // SQLite does not support LIMIT in UPDATE queries
            $query->setLimit(new Limit());
        }

        parent::postHandleQuery($query, $context);
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

        // SQLite does not support UPDATE ... FROM aliases
        if ($context->getCurrentSqlQuery()->getResolvedAction() === QueryInterface::ACTION_UPDATE) {
            $subject->setSubjectAlias('');
        }
    }
}
