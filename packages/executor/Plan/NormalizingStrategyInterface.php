<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;

interface NormalizingStrategyInterface
{
    public function buildNormalizingCommand(BasicQueryInterface $query, NormalizingPlanInterface $plan): QueryCommandInterface;
}
