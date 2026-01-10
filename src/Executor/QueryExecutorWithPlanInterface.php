<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\Plan\ExecutionPlanAwareInterface;
use IfCastle\AQL\Executor\Plan\NormalizingPlanAwareInterface;
use IfCastle\AQL\Executor\Plan\QueryCommandInterface;
use IfCastle\AQL\Executor\Plan\QueryPlanAwareInterface;
use IfCastle\AQL\Executor\PostAction\PostActionsAbleInterface;

interface QueryExecutorWithPlanInterface extends QueryExecutorInterface,
    ExecutionPlanAwareInterface,
    QueryPlanAwareInterface,
    NormalizingPlanAwareInterface,
    PostActionsAbleInterface
{
    /**
     * Returns the execution command for the current query.
     *
     *
     */
    public function resolveQueryCommand(BasicQueryInterface $currentQuery, ?BasicQueryInterface $mainQuery = null): QueryCommandInterface;
}
