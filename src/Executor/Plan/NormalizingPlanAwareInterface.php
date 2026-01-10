<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface NormalizingPlanAwareInterface
{
    public function getNormalizingPlan(): NormalizingPlanInterface;

    public function findNormalizingPlan(): ?NormalizingPlanInterface;
}
