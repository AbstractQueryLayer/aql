<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface ExecutionPlanAwareInterface
{
    public function getExecutionPlan(): ExecutionPlanInterface;

    public function findExecutionPlan(): ?ExecutionPlanInterface;
}
