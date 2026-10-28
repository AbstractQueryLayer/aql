<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

use Async\Signal;

use function Async\await_all_or_fail;
use function Async\await_any_or_fail;
use function Async\signal;
use function Async\spawn;

/**
 * The body of a worker process: runs its workers side by side until one fails or the process is asked to stop.
 *
 * Each worker loops in a coroutine of its own, so a slow kind does not hold up the kinds of another worker. SIGTERM
 * or SIGINT stops every worker between jobs (JobWorker::stop()) and the command exits 0, so a service manager that
 * restarts on failure does not restart a requested stop. The first exception of a worker cancels the others and
 * exits 1, naming only the exception's class: storage and transport messages may carry credentials or payloads.
 */
final class WorkerCommand
{
    /**
     * @param string $name printed as "<name> stopped: <ExceptionClass>" when a worker fails
     * @param \Closure(): list<JobWorker> $workers builds the workers; an exception it throws is reported as a
     *     worker's, so a bad configuration and a lost connection read the same
     * @param int $idleMs each worker's pause when no job is due, milliseconds
     * @param resource $errors where the failure line goes
     * @return int the exit status: 0 after a requested stop, 1 after a failure
     */
    public static function run(string $name, \Closure $workers, int $idleMs = 1000, mixed $errors = \STDERR): int
    {
        $loops = [];
        $signals = null;
        try {
            $built = $workers();
            $signals = spawn(static function () use ($built): void {
                await_any_or_fail([signal(Signal::SIGTERM), signal(Signal::SIGINT)]);
                foreach ($built as $worker) {
                    $worker->stop();
                }
            });
            foreach ($built as $worker) {
                $loops[] = spawn($worker->runUntil(...), $idleMs);
            }

            // A loop returns only after stop(), which stops them all.
            await_any_or_fail($loops);
            await_all_or_fail($loops);

            return 0;
        } catch (\Throwable $error) {
            foreach ($loops as $loop) {
                $loop->cancel();
            }

            \fwrite($errors, $name . ' stopped: ' . $error::class . "\n");

            return 1;
        } finally {
            $signals?->cancel();
        }
    }
}
