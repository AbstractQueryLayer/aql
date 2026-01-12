<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

/**
 * The execution context can be the context of the current coroutine.
 * It provides an abstraction over variables stored in the context.
 */
interface ContextVariablesInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;
}
