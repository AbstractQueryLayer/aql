<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/**
 * One step of a worker loop: recover expired leases, let the schedulers queue what is due, lease one job and run its
 * handler while a coroutine renews the lease.
 *
 * The renewal runs every third of the lease. When a renewal finds the lease gone, the handler's coroutine is cancelled:
 * the attempt is no longer this worker's, and whatever it writes next would race the new owner. A handler that must
 * not write after losing its lease still checks the lease itself before such a write; renewal under cooperative
 * scheduling is best effort.
 *
 * stop() ends runUntil() between jobs: the job running then finishes and is recorded, so a stopped process leaves
 * no lease to expire.
 */
final class JobWorker
{
    /** Job states after which the queue never runs the job again. */
    private const array FINAL_STATES = ['succeeded', 'failed'];
    /** What runUntil() returns when stop() ended it. */
    public const string STOPPED = 'stopped';

    private readonly int $leaseSeconds;
    private bool $stopping = false;

    /**
     * @param array<string, \Closure(LeasedJob): void> $handlers by job kind; a handler throws to fail the attempt
     * @param list<\Closure(JobQueue): void> $schedulers run before each lease, to queue jobs that are due
     * @param ?int $leaseSeconds renewal runs every third of it; null takes the queue's lease length
     */
    public function __construct(
        private readonly JobQueue $queue,
        private readonly array $handlers,
        private readonly array $schedulers = [],
        ?int $leaseSeconds = null,
    ) {
        $this->leaseSeconds = $leaseSeconds ?? $queue->leaseSeconds();
    }

    /**
     * Runs at most one job.
     *
     * @return bool false when no job was due
     */
    public function runOnce(): bool
    {
        $this->queue->recoverExpired();
        foreach ($this->schedulers as $scheduler) {
            $scheduler($this->queue);
        }

        $job = $this->queue->lease(\array_keys($this->handlers));
        if ($job === null) {
            return false;
        }

        $this->run($job, $this->handlers[$job->kind]);

        return true;
    }

    /**
     * Runs due jobs one at a time, pausing while none is due, until job $jobId reaches a final state or stop() is
     * called; without a job id it returns only by stop() or by an exception from runOnce(), such as a lost database
     * connection.
     *
     * @param int $idleMs pause when no job is due, milliseconds; a stop() during the pause takes effect after it
     * @param ?int $jobId the job to wait for, as the queue numbers it
     * @return string the job's final state, succeeded or failed, or STOPPED
     */
    public function runUntil(int $idleMs, ?int $jobId = null): string
    {
        while (!$this->stopping) {
            if ($jobId !== null && \in_array($state = $this->queue->state($jobId), self::FINAL_STATES, true)) {
                return $state;
            }

            if (!$this->runOnce()) {
                delay($idleMs);
            }
        }

        return self::STOPPED;
    }

    /** Makes runUntil() return before its next job; the job running now finishes first. */
    public function stop(): void
    {
        $this->stopping = true;
    }

    private function run(LeasedJob $job, \Closure $handler): void
    {
        $work = spawn(static fn() => $handler($job));
        $renewal = spawn(function () use ($job, $work): void {
            while (!$work->isCompleted()) {
                delay(\intdiv($this->leaseSeconds * 1000, 3));
                // @phpstan-ignore booleanNot.alwaysTrue (the handler's coroutine may complete during the delay)
                if (!$work->isCompleted() && !$this->queue->renew($job)) {
                    $work->cancel();
                    return;
                }
            }
        });

        try {
            await($work);
            $renewal->cancel();
            $this->queue->complete($job);
        } catch (\Throwable $error) {
            $renewal->cancel();
            $this->queue->fail($job, $error);
        }
    }
}
