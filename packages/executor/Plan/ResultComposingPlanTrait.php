<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\ExecutionPlan\InsertPositionEnum;

trait ResultComposingPlanTrait
{
    abstract public function addStageHandler(
        string $stage,
        mixed $handler,
        InsertPositionEnum $insertPosition = InsertPositionEnum::TO_END
    ): static;

    public function addResultComposer(ResultHandlerInterface $handler): static
    {
        return $this->addStageHandler(ResultComposingPlanInterface::RESULT_COMPOSER, $handler);
    }

    public function addResultPostReader(ResultReaderInterface $reader): static
    {
        return $this->addStageHandler(ResultComposingPlanInterface::POST_READER, $reader);
    }
}
