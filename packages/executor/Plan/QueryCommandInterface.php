<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;

interface QueryCommandInterface extends CommandInterface, ResultProcessingPlanAwareInterface
{
    public function getContainedQuery(): BasicQueryInterface;
}
