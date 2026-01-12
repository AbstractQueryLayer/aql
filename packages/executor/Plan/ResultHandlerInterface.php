<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\Handler\InvokableInterface;

interface ResultHandlerInterface extends InvokableInterface
{
    public function handleResult(ExecutionContextInterface $context): void;
}
