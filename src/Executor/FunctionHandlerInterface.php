<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface FunctionHandlerInterface
{
    /**
     * Handles a reference to a Function of Entity or DataBase.
     *
     *
     */
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void;
}
