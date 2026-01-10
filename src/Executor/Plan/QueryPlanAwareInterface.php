<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface QueryPlanAwareInterface
{
    public function getQueryPlan(): QueryPlanInterface;
}
