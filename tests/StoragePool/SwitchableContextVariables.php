<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;

/** Variables of one execution context; startNewContext() ends it, as a finished coroutine drops its context. */
final class SwitchableContextVariables implements ContextVariablesInterface
{
    /** @var array<string, mixed> */
    private array $variables        = [];

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

    public function startNewContext(): void
    {
        $this->variables            = [];
    }
}
