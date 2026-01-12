<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

interface SqlQueryCommandInterface extends QueryCommandInterface
{
    public function getContainedSqlQuery(): QueryInterface;
}
