<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface ResultProcessingPlanAwareInterface
{
    public function getResultProcessingPlan(): ResultProcessingPlanInterface;
}
