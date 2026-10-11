<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Executor\SqlQueryExecutor;

/**
 * The SQL executor for PostgreSQL.
 *
 * A shared locking read is `FOR SHARE` here: PostgreSQL has no `LOCK IN SHARE MODE`.
 * The form is chosen when the SQL is rendered; the query object keeps its own form.
 */
class PostgreSqlQueryExecutor extends SqlQueryExecutor
{
    #[\Override]
    protected function renderSql(BasicQueryInterface $query): string
    {
        $query                      = $query->resolveNode();

        if ($query instanceof SelectInterface) {
            return $query->getResultWithForShare();
        }

        return parent::renderSql($query);
    }
}
