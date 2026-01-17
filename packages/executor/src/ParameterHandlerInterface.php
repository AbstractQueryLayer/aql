<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface ParameterHandlerInterface
{
    /**
     * Handles a parameter.
     */
    public function handleParameter(ConstantInterface $constant, NodeContextInterface $context): void;
}
