<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\ExecutionPlan\InsertPositionEnum;

trait ResultProcessingPlanTrait
{
    abstract public function addStageHandler(
        string $stage,
        mixed $handler,
        InsertPositionEnum $insertPosition = InsertPositionEnum::TO_END
    ): static;

    public function addResultRawReader(ResultHandlerInterface $handler): static
    {
        return $this->addStageHandler(stage: ResultProcessingPlanInterface::RAW_READER, handler: $handler);
    }

    public function addRowModifier(RowModifierInterface $handler): static
    {
        return $this->addStageHandler(stage: ResultProcessingPlanInterface::ROW_MODIFIER, handler: $handler);
    }

    public function addResultReader(ResultReaderInterface $reader): static
    {
        return $this->addStageHandler(stage: ResultProcessingPlanInterface::RESULT_READER, handler: $reader);
    }
}
