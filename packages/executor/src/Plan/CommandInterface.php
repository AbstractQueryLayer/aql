<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutorAwareInterface;
use IfCastle\DesignPatterns\Handler\HandlerWithHashInterface;
use IfCastle\DesignPatterns\Handler\InvokableInterface;
use IfCastle\DI\DisposableInterface;

/**
 * Interface CommandInterface.
 *
 * A command is an action that will be executed.
 * A command is usually a request to the Database.
 * The command has its own Pre- and Post-handlers, which are provided by the BeforeAfterExecutorAwareInterface.
 */
interface CommandInterface extends BeforeAfterExecutorAwareInterface,
    ExecutionHandlerInterface,
    InvokableInterface,
    HandlerWithHashInterface,
    DisposableInterface
{
    /**
     * Name of command stage.
     */
    public function getCommandStage(): string;
}
