<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Executor\Context\ContextVariablesInterface;

use function Async\coroutine_context;
use function Async\current_coroutine;

/**
 * The variables of the current TrueAsync coroutine, for a StorageCollectionPool: a value is read from the coroutine
 * that set it only, so a coroutine it starts begins with none and borrows storages of its own.
 *
 * Every method starts the TrueAsync scheduler if it has not started yet.
 */
final class CoroutineContextVariables implements ContextVariablesInterface
{
    #[\Override]
    public function get(string $key): mixed
    {
        return coroutine_context()->findLocal($key);
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        coroutine_context()->set($key, $value, replace: true);
    }

    /**
     * Runs the callback when the current coroutine completes, cancellation included. An error the callback throws,
     * such as a warning an error handler turned into an exception, goes to error_log(): thrown out of a completion
     * callback it would stop the scheduler and every other coroutine with it.
     */
    #[\Override]
    public function defer(callable $callback): void
    {
        // Starts the scheduler in a CLI process that has not started it: current_coroutine() fails before.
        coroutine_context();
        current_coroutine()->finally(static function () use ($callback): void {
            try {
                $callback();
            } catch (\Throwable $error) {
                \error_log('A deferred callback of a coroutine failed: ' . $error);
            }
        });
    }
}
