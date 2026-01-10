<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

interface NormalizingPlanInterface extends ExecutionPlanInterface
{
    public function defineNormalizingQuery(BasicQueryInterface $query): BasicQueryInterface;

    public function defineNormalizingCommand(BasicQueryInterface $query): QueryCommandInterface;

    public function defineNormalizingSqlCommand(QueryInterface $query): SqlQueryCommandInterface;
}
