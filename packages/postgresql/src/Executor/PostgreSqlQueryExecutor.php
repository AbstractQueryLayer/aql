<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\SqlQueryExecutor;

/**
 * The SQL executor for PostgreSQL.
 *
 * A shared locking read is `FOR SHARE` here: PostgreSQL has no `LOCK IN SHARE MODE`.
 */
class PostgreSqlQueryExecutor extends SqlQueryExecutor
{
    #[\Override]
    public function postHandleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        $query                      = $query->resolveNode();

        if ($query instanceof SelectInterface) {
            $query->renderShareAsForShare();
        }

        parent::postHandleQuery($query, $context);
    }
}
