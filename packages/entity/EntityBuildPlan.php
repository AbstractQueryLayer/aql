<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterPlanWithMapping;
use IfCastle\DesignPatterns\ExecutionPlan\HandlerExecutorCallable;

class EntityBuildPlan extends BeforeAfterPlanWithMapping implements EntityBuildPlanInterface
{
    public function __construct()
    {
        parent::__construct(
            new HandlerExecutorCallable(),
            [
                self::STEP_START,
                self::STEP_ASPECTS,
                self::STEP_PROPERTIES,
                self::STEP_INHERIT,
                self::STEP_AFTER_PROPERTIES,
                self::STEP_KEYS,
                self::STEP_FUNCTIONS,
                self::STEP_MODIFIERS,
                self::STEP_RELATIONS,
                self::STEP_AFTER_RELATIONS,
                self::STEP_CONSTRAINTS,
                self::STEP_ACTIONS,
                self::STEP_END,
            ]
        );
    }

    #[\Override]
    public function dispose(): void
    {
        $this->handlers = [];
    }
}
