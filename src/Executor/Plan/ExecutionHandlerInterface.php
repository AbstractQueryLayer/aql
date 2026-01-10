<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface ExecutionHandlerInterface
{
    public function executeHandler(?ExecutionContextInterface $context = null): void;
}
