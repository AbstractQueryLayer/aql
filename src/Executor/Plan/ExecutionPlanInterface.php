<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterActionInterface;
use IfCastle\DesignPatterns\ExecutionPlan\ExecutionPlanWithMappingInterface;
use IfCastle\DI\DisposableInterface;

interface ExecutionPlanInterface extends ExecutionPlanWithMappingInterface,
    BeforeAfterActionInterface,
    ResultProcessingPlanInterface,
    ResultComposingPlanInterface,
    DisposableInterface
{
    /**
     * Left SQL queries. That is, these are requests that will be executed AFTER the main ones.
     *
     * @var string
     */
    final public const string LEFT          = 'l';

    /**
     * At this stage, the main or target SQL queries will be executed.
     *
     * @var string
     */
    final public const string TARGET        = 't';

    /**
     * Queries for the right side.
     * That is, these are SQL queries that must be executed before the main queries are executed.
     *
     * @var string
     */
    final public const string RIGHT         = 'r';

    final public const string POST_ACTION   = 'a';

    /**
     * A special stage of work that is ALWAYS performed, even if an error occurs.
     *
     * @var string
     */
    final public const string FINALLY       = 'f';

    public function getParentPlan(): ?ExecutionPlanInterface;

    public function setParentPlan(ExecutionPlanInterface $plan): static;

    public function findCommandByHash(string|int|null $hash): ?CommandInterface;

    public function findCommandByQuery(BasicQueryInterface $query): ?QueryCommandInterface;

    public function addCommand(
        CommandInterface $command,
        ?CommandInterface $afterCommand      = null,
        ?CommandInterface $beforeCommand     = null,
        bool             $toStart           = false
    ): static;

    public function executePlanAndReturnResult(): ResultInterface;

    public function getExecutionContext(): ?ExecutionContextInterface;
}
