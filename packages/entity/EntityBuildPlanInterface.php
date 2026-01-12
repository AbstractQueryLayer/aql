<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterActionInterface;
use IfCastle\DI\DisposableInterface;

interface EntityBuildPlanInterface extends BeforeAfterActionInterface, DisposableInterface
{
    /**
     * First step of build.
     * @var string
     */
    final public const string STEP_START = 'start';

    /**
     * @var string
     */
    final public const string STEP_ASPECTS = 'aspects';

    /**
     * Property step.
     * @var string
     */
    final public const string STEP_PROPERTIES = 'properties';

    /**
     * Copy inherited elements.
     * @var string
     */
    final public const string STEP_INHERIT = 'inherit';

    /**
     * @var string
     */
    final public const string STEP_AFTER_PROPERTIES = 'afterProperties';

    /**
     * @var string
     */
    final public const string STEP_KEYS = 'keys';

    /**
     * @var string
     */
    final public const string STEP_FUNCTIONS = 'functions';

    /**
     * @var string
     */
    final public const string STEP_MODIFIERS = 'modifiers';

    /**
     * @var string
     */
    final public const string STEP_RELATIONS = 'relations';

    /**
     * @var string
     */
    final public const string STEP_AFTER_RELATIONS = 'afterRelations';

    /**
     * @var string
     */
    final public const string STEP_CONSTRAINTS = 'constraints';

    /**
     * @var string
     */
    final public const string STEP_ACTIONS = 'actions';

    /**
     * @var string
     */
    final public const string STEP_END = 'end';
}
