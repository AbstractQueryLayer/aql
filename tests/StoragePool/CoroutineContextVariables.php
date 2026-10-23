<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use Async\Coroutine;
use IfCastle\AQL\Executor\Context\ContextVariablesInterface;

use function Async\current_coroutine;

/** Variables of the current coroutine, dropped with it; a coroutine it starts begins with none. */
final class CoroutineContextVariables implements ContextVariablesInterface
{
    /** @var \WeakMap<Coroutine, array<string, mixed>> */
    private \WeakMap $variables;

    public function __construct()
    {
        $this->variables            = new \WeakMap();
    }

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->variables[current_coroutine()][$key] ?? null;
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $coroutine                  = current_coroutine();
        $variables                  = $this->variables[$coroutine] ?? [];
        $variables[$key]            = $value;
        $this->variables[$coroutine] = $variables;
    }
}
