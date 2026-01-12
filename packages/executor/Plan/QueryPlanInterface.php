<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;

/**
 * Interface for creating SQL commands of the query execution plan.
 */
interface QueryPlanInterface
{
    public function newQueryCommand(BasicQueryInterface $query, string $target): QueryCommandInterface;

    public function newQueryTargetCommand(BasicQueryInterface $query): QueryCommandInterface;

    public function newQueryRightCommand(BasicQueryInterface $query): QueryCommandInterface;

    public function newQueryLeftCommand(BasicQueryInterface $query): QueryCommandInterface;
}
