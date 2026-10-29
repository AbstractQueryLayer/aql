<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;

/**
 * Variables of one execution context; startNewContext() ends it, running its deferred callbacks, as a finished
 * coroutine does.
 */
final class SwitchableContextVariables implements ContextVariablesInterface
{
    /** @var array<string, mixed> */
    private array $variables        = [];

    /** @var list<callable(): void> */
    private array $deferred         = [];

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->variables[$key] ?? null;
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    #[\Override]
    public function defer(callable $callback): void
    {
        $this->deferred[]           = $callback;
    }

    public function startNewContext(): void
    {
        $deferred                   = $this->deferred;
        $this->deferred             = [];

        foreach ($deferred as $callback) {
            $callback();
        }

        $this->variables            = [];
    }
}
