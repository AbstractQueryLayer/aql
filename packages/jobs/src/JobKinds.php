<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

/**
 * The job kinds of one worker: a handler per kind and the schedulers that queue them.
 *
 * The kinds a worker handles are the kinds it leases, so two workers of different database accounts share one jobs
 * table without taking each other's jobs. The registry refuses a second handler for a kind and a periodic kind it does
 * not handle; a kind handled by two workers is the application's to prevent.
 */
final class JobKinds
{
    /** The longest kind the jobs table holds, in ASCII bytes. */
    public const int MAX_KIND_LENGTH = 64;

    /** @var array<string, \Closure(LeasedJob): void> */
    private array $handlers = [];
    /** @var list<\Closure(JobQueue): void> */
    private array $schedulers = [];

    /**
     * Registers the handler of $kind.
     *
     * @param \Closure(LeasedJob): void $handler throws to fail the attempt
     * @throws \LogicException when $kind already has a handler or is not 1 to MAX_KIND_LENGTH printable ASCII bytes
     */
    public function handle(string $kind, \Closure $handler): self
    {
        if (\preg_match('/^[\x21-\x7e]{1,' . self::MAX_KIND_LENGTH . '}$/', $kind) !== 1) {
            throw new \LogicException('A job kind is 1 to ' . self::MAX_KIND_LENGTH . ' printable ASCII bytes');
        }

        if (\array_key_exists($kind, $this->handlers)) {
            throw new \LogicException('Job kind ' . $kind . ' already has a handler');
        }

        $this->handlers[$kind] = $handler;

        return $this;
    }

    /**
     * Queues $kind with an empty payload unless a job of it finished within the last $periodSeconds
     * (JobQueue::enqueueEvery()).
     *
     * @throws \LogicException when $kind has no handler here: the job would wait for a worker that never leases it
     */
    public function every(string $kind, int $periodSeconds): self
    {
        if (!\array_key_exists($kind, $this->handlers)) {
            throw new \LogicException('Job kind ' . $kind . ' is scheduled but not handled');
        }

        return $this->schedule(static fn(JobQueue $queue) => $queue->enqueueEvery($kind, $periodSeconds));
    }

    /**
     * Adds a scheduler, run before each lease to queue the jobs that are due.
     *
     * @param \Closure(JobQueue): void $scheduler
     */
    public function schedule(\Closure $scheduler): self
    {
        $this->schedulers[] = $scheduler;

        return $this;
    }

    /** @return list<string> the kinds with a handler, in registration order */
    public function kinds(): array
    {
        return \array_keys($this->handlers);
    }

    /**
     * A worker that leases and runs these kinds on $queue.
     *
     * @param ?int $leaseSeconds null takes the queue's lease length
     */
    public function worker(JobQueue $queue, ?int $leaseSeconds = null): JobWorker
    {
        return new JobWorker($queue, $this->handlers, $this->schedulers, $leaseSeconds);
    }
}
