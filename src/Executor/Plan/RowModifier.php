<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DI\DisposableInterface;

final class RowModifier implements RowModifierInterface, DisposableInterface
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback             = $callback;
    }

    #[\Override]
    public function modifyResultRows(array &$rows, ExecutionContextInterface $context): void
    {
        ($this->callback)($rows, $context);
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->modifyResultRows(...$args);
        return null;
    }

    #[\Override]
    public function dispose(): void
    {
        $this->callback             = null;
    }
}
