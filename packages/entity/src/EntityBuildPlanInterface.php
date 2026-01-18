<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterActionInterface;
use IfCastle\DI\DisposableInterface;

interface EntityBuildPlanInterface extends BeforeAfterActionInterface, DisposableInterface
{
    /**
     * First step of build.
     */
    final public const string STEP_START = 'start';

    final public const string STEP_ASPECTS = 'aspects';

    /**
     * Property step.
     */
    final public const string STEP_PROPERTIES = 'properties';

    /**
     * Copy inherited elements.
     */
    final public const string STEP_INHERIT = 'inherit';

    final public const string STEP_AFTER_PROPERTIES = 'afterProperties';

    final public const string STEP_KEYS = 'keys';

    final public const string STEP_FUNCTIONS = 'functions';

    final public const string STEP_MODIFIERS = 'modifiers';

    final public const string STEP_RELATIONS = 'relations';

    final public const string STEP_AFTER_RELATIONS = 'afterRelations';

    final public const string STEP_CONSTRAINTS = 'constraints';

    final public const string STEP_ACTIONS = 'actions';

    final public const string STEP_END = 'end';
}
