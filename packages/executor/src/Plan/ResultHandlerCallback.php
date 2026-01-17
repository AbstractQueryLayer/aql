<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DI\DisposableInterface;

final class ResultHandlerCallback implements ResultHandlerInterface, DisposableInterface
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback             = $callback;
    }

    #[\Override]
    public function handleResult(ExecutionContextInterface $context): void
    {
        ($this->callback)($context);
    }

    #[\Override]
    public function dispose(): void
    {
        $this->callback             = null;
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->handleResult(...$args);
        return null;
    }
}
