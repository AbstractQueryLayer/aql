<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface ResultComposingPlanAwareInterface
{
    public function getResultComposingPlan(): ResultComposingPlanInterface;
}
