<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\ExecutionPlan\ExecutionPlan;
use IfCastle\DesignPatterns\ExecutionPlan\HandlerExecutorInterface;

class ResultProcessingPlan extends ExecutionPlan implements ResultProcessingPlanInterface
{
    use ResultProcessingPlanTrait;

    public function __construct(HandlerExecutorInterface $handlerExecutor)
    {
        parent::__construct($handlerExecutor, [self::RAW_READER, self::ROW_MODIFIER, self::RESULT_READER]);
    }
}
