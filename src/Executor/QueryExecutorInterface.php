<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\DI\DisposableInterface;

interface QueryExecutorInterface extends DisposableInterface
{
    public function executeQuery(
        BasicQueryInterface                                       $query,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): ResultInterface;

    public function preprocessing(
        BasicQueryInterface                                       $query,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): void;
}
