<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Transaction\IsolationLevelEnum;

/**
 * Background jobs on the Job and JobAttempt entities (README.md, the schema contract), on the database clock: workers
 * on several hosts agree on due times and leases because no time comes from PHP.
 *
 * A worker leases the first due queued job with SELECT … FOR UPDATE SKIP LOCKED, so two workers never take one row,
 * and stamps it with a fresh random lease token. Every later step of that attempt (renew, complete, fail) names the
 * token: a worker that lost its lease, even one that leases the same job again under the same worker id, cannot
 * finish an attempt that is no longer its own. A lease that expires returns the job to the queue through
 * recoverExpired(). Each finished attempt leaves one job_attempts row.
 */
final class JobQueue
{
    /** Retry delay after the first failed attempt, seconds; it doubles per attempt up to MAX_BACKOFF. */
    public const int BASE_BACKOFF = 30;
    public const int MAX_BACKOFF = 3600;
    /** Error texts are fixed per class: a transport message may carry payload or document text. */
    private const string ERROR_FORMAT = '%s: attempt failed';

    /** %s is the due time: after {delay} seconds, or at {runAt}. */
    private const string INSERT = 'INSERT INTO Job (kind, payload, priority, runAfter, maxAttempts, dedupKey,'
        . ' subjectKind, subjectId) VALUES ({kind}, {payload}, {priority}, %s, {max}, {dedup}, {subjectKind},'
        . ' {subjectId})';
    private const string AFTER_DELAY = 'UTC_TIMESTAMP(6) + INTERVAL {delay} SECOND';
    /** idx_jobs_subject (subjectKind, subjectId). */
    private const string QUEUED_FOR = 'SELECT id, payload, runAfter FROM Job WHERE subjectKind = {subjectKind}'
        . " AND subjectId = {subjectId} AND kind = {kind} AND state = 'queued' ORDER BY runAfter, id";
    private const string ACTIVE_FOR = 'SELECT id, state, payload, runAfter FROM Job WHERE subjectKind = {subjectKind}'
        . " AND subjectId = {subjectId} AND kind = {kind} AND state IN ('queued', 'running') ORDER BY id FOR UPDATE";
    private const string MOVE = 'UPDATE Job SET runAfter = {runAt}, payload = {payload}, dedupKey = {dedup}'
        . " WHERE id = {id} AND state = 'queued'";
    private const string RUN_AFTER = 'SELECT runAfter FROM Job WHERE id = {id}';
    private const string CANCEL_FOR = "UPDATE Job SET state = 'cancelled', dedupKey = NULL,"
        . ' finishedAt = UTC_TIMESTAMP(6) WHERE subjectKind = {subjectKind} AND subjectId = {subjectId}'
        . " AND kind = {kind} AND state = 'queued'";
    /** idx_jobs_queue (state, priority, runAfter, id) gives the order; %s is the kind filter. */
    private const string DUE = "SELECT id, kind, payload, attempts FROM Job WHERE state = 'queued'"
        . ' AND runAfter <= UTC_TIMESTAMP(6)%s ORDER BY priority, runAfter, id LIMIT 1 FOR UPDATE SKIP LOCKED';
    private const string TAKE = "UPDATE Job SET state = 'running', attempts = attempts + 1, lockedBy = {worker},"
        . ' lockedUntil = UTC_TIMESTAMP(6) + INTERVAL {lease} SECOND, leaseToken = {token},'
        . ' startedAt = UTC_TIMESTAMP(6) WHERE id = {id}';
    private const string RENEW = 'UPDATE Job SET lockedUntil = UTC_TIMESTAMP(6) + INTERVAL {lease} SECOND'
        . " WHERE id = {id} AND state = 'running' AND leaseToken = {token}";
    private const string HELD = 'SELECT attempts, maxAttempts, lockedBy, startedAt FROM Job WHERE id = {id}'
        . " AND state = 'running' AND leaseToken = {token} FOR UPDATE";
    private const string SUCCEED = "UPDATE Job SET state = 'succeeded', lockedBy = NULL, lockedUntil = NULL,"
        . ' leaseToken = NULL, dedupKey = NULL, lastError = NULL, finishedAt = UTC_TIMESTAMP(6) WHERE id = {id}';
    private const string REQUEUE = "UPDATE Job SET state = 'queued', lockedBy = NULL, lockedUntil = NULL,"
        . ' leaseToken = NULL, lastError = {error}, runAfter = UTC_TIMESTAMP(6) + INTERVAL {delay} SECOND'
        . ' WHERE id = {id}';
    private const string GIVE_UP = "UPDATE Job SET state = 'failed', lockedBy = NULL, lockedUntil = NULL,"
        . ' leaseToken = NULL, dedupKey = NULL, lastError = {error}, finishedAt = UTC_TIMESTAMP(6) WHERE id = {id}';
    private const string ATTEMPT = 'INSERT INTO JobAttempt (jobId, attempt, worker, startedAt, finishedAt, outcome,'
        . ' error) VALUES ({id}, {attempt}, {worker}, {started}, UTC_TIMESTAMP(6), {outcome}, {error})';
    private const string STATE = 'SELECT state FROM Job WHERE id = {id}';
    private const string RUNNING = "SELECT id FROM Job WHERE kind = {kind} AND state = 'running' LIMIT 1";
    private const string RECENT = 'SELECT id FROM Job WHERE kind = {kind}'
        . ' AND finishedAt > UTC_TIMESTAMP(6) - INTERVAL {seconds} SECOND LIMIT 1';
    /** idx_jobs_lease (state, lockedUntil). */
    private const string EXPIRED = 'SELECT id, attempts, maxAttempts, lockedBy, startedAt FROM Job'
        . " WHERE state = 'running' AND lockedUntil < UTC_TIMESTAMP(6) ORDER BY lockedUntil LIMIT {limit}"
        . ' FOR UPDATE SKIP LOCKED';

    /**
     * @param string $workerId names this worker in jobs.lockedBy and job_attempts.worker, at most 64 ASCII bytes
     * @param int $leaseSeconds how long a lease lasts without renew()
     */
    public function __construct(
        private readonly JobDatabaseInterface $database,
        private readonly string $workerId,
        private readonly int $leaseSeconds = 120,
    ) {}

    /** How long a lease lasts without renew(), seconds. */
    public function leaseSeconds(): int
    {
        return $this->leaseSeconds;
    }

    /**
     * Queues a job.
     *
     * @param array<string, mixed> $payload stored as JSON
     * @param ?string $dedupKey while a job with this key is queued or running, another one is not queued
     * @param int $delaySeconds the job is due this many seconds from now
     * @param ?string $runAt the job is due at this UTC time, `Y-m-d H:i:s`, in place of $delaySeconds
     * @return ?int the new job id, or null when $dedupKey belongs to an active job
     */
    public function enqueue(string $kind, array $payload, ?string $dedupKey = null, int $priority = 0,
        int $delaySeconds = 0, int $maxAttempts = 5, ?string $subjectKind = null, ?int $subjectId = null,
        ?string $runAt = null): ?int
    {
        $insert = \sprintf(self::INSERT, $runAt === null ? self::AFTER_DELAY : '{runAt}');
        $parameters = ['kind' => $kind, 'payload' => self::payload($payload), 'priority' => $priority,
            'max' => $maxAttempts, 'dedup' => $dedupKey, 'subjectKind' => $subjectKind, 'subjectId' => $subjectId]
            + ($runAt === null ? ['delay' => $delaySeconds] : ['runAt' => $runAt]);
        try {
            $result = $this->database->query($insert, $parameters);
        } catch (DuplicateKeysException) {
            return null;
        }

        $key = $result instanceof InsertUpdateResultInterface ? $result->getLastPrimaryKey() : null;

        return \is_array($key) ? (int) $key['id'] : throw new \LogicException('The job insert returned no id');
    }

    /**
     * Queued jobs of $kind about one subject, soonest first.
     *
     * @return list<array{id: int, payload: array<string, mixed>, runAfter: string}>
     */
    public function queuedFor(string $kind, string $subjectKind, int $subjectId): array
    {
        return \array_map(static fn(array $row): array => ['id' => (int) $row['id'],
            'payload' => \json_decode($row['payload'], true, 32, \JSON_THROW_ON_ERROR), 'runAfter' => $row['runAfter']],
            $this->database->select(self::QUEUED_FOR, ['kind' => $kind, 'subjectKind' => $subjectKind,
                'subjectId' => $subjectId]));
    }

    /**
     * The queued and running jobs of $kind about one subject, locked for update, oldest first. In the caller's
     * transaction: a worker's lease skips a locked job, so a queued one stays queued until the caller commits.
     *
     * @return list<array{id: int, state: string, payload: array<string, mixed>, runAfter: string}>
     */
    public function lockActiveFor(string $kind, string $subjectKind, int $subjectId): array
    {
        return \array_map(static fn(array $row): array => ['id' => (int) $row['id'], 'state' => $row['state'],
            'payload' => \json_decode($row['payload'], true, 32, \JSON_THROW_ON_ERROR), 'runAfter' => $row['runAfter']],
            $this->database->select(self::ACTIVE_FOR, ['kind' => $kind, 'subjectKind' => $subjectKind,
                'subjectId' => $subjectId]));
    }

    /**
     * Moves a queued job, locked by lockActiveFor(), to $runAt (UTC, `Y-m-d H:i:s`) with a new payload and dedup key.
     *
     * @param array<string, mixed> $payload stored as JSON
     */
    public function move(int $id, string $runAt, array $payload, ?string $dedupKey): void
    {
        $this->database->query(self::MOVE, ['id' => $id, 'runAt' => $runAt, 'payload' => self::payload($payload),
            'dedup' => $dedupKey]);
    }

    /** When the job is due, as the jobs table stores it (UTC, microseconds); null when there is no such job. */
    public function runAfter(int $id): ?string
    {
        return $this->database->select(self::RUN_AFTER, ['id' => $id])[0]['runAfter'] ?? null;
    }

    /** Cancels the queued jobs of $kind about one subject; a running one finishes. Returns how many. */
    public function cancelFor(string $kind, string $subjectKind, int $subjectId): int
    {
        return $this->database->query(self::CANCEL_FOR, ['kind' => $kind, 'subjectKind' => $subjectKind,
            'subjectId' => $subjectId])->affected();
    }

    /**
     * A JobWorker scheduler's step: queues a job of $kind, with an empty payload and $kind as its dedup key, unless one
     * finished within the last $periodSeconds; the dedup key keeps one active at a time.
     */
    public function enqueueEvery(string $kind, int $periodSeconds): void
    {
        if (!$this->finishedWithin($kind, $periodSeconds)) {
            $this->enqueue($kind, [], $kind);
        }
    }

    /** The job's state (queued, running, succeeded, failed), or null when there is no such job. */
    public function state(int $id): ?string
    {
        return $this->database->select(self::STATE, ['id' => $id])[0]['state'] ?? null;
    }

    /** True when a job of $kind finished, succeeded or failed, within the last $seconds. */
    public function finishedWithin(string $kind, int $seconds): bool
    {
        return $this->database->select(self::RECENT, ['kind' => $kind, 'seconds' => $seconds]) !== [];
    }

    /** Whether a job of $kind holds a lease now (an expired lease still counts until recoverExpired() runs). */
    public function running(string $kind): bool
    {
        return $this->database->select(self::RUNNING, ['kind' => $kind]) !== [];
    }

    /**
     * Leases the first due queued job, or returns null when none is due.
     *
     * @param ?array<string> $kinds only jobs of these kinds; null takes any kind. A worker names the kinds it has
     *     handlers for, so it never leases, and fails as unknown, a job meant for a worker of another account.
     */
    public function lease(?array $kinds = null): ?LeasedJob
    {
        if ($kinds === []) {
            return null;
        }

        $due = \sprintf(self::DUE, $kinds === null ? '' : ' AND kind IN {kinds}');
        $parameters = $kinds === null ? [] : ['kinds' => \array_values($kinds)];

        return $this->database->transaction(function (JobDatabaseInterface $database) use ($due,
            $parameters): ?LeasedJob {
            $row = $database->select($due, $parameters)[0] ?? null;
            if ($row === null) {
                return null;
            }

            $token = \random_bytes(16);
            $database->query(self::TAKE, ['worker' => $this->workerId, 'lease' => $this->leaseSeconds,
                'token' => $token, 'id' => $row['id']]);

            return new LeasedJob((int) $row['id'], $row['kind'],
                \json_decode($row['payload'], true, flags: \JSON_THROW_ON_ERROR), (int) $row['attempts'] + 1, $token);
        }, IsolationLevelEnum::COMMITTED);
    }

    /** Extends the lease by the lease length from now; false when the lease is no longer this job's. */
    public function renew(LeasedJob $job): bool
    {
        return $this->database->query(self::RENEW, ['lease' => $this->leaseSeconds, 'id' => $job->id,
            'token' => $job->token])->affected() === 1;
    }

    /** Marks the attempt succeeded; false when the lease is no longer this job's, and nothing changes then. */
    public function complete(LeasedJob $job): bool
    {
        return $this->finish($job, function (JobDatabaseInterface $database, array $held) use ($job): void {
            $database->query(self::SUCCEED, ['id' => $job->id]);
            $this->recordAttempt($database, $job->id, $held, 'succeeded', null);
        });
    }

    /**
     * Records a failed attempt: the job is queued again after a backoff, or fails for good after maxAttempts.
     *
     * @return bool false when the lease is no longer this job's, and nothing changes then
     */
    public function fail(LeasedJob $job, \Throwable $error): bool
    {
        $message = \sprintf(self::ERROR_FORMAT, $error::class);

        return $this->finish($job, function (JobDatabaseInterface $database, array $held) use ($job, $message): void {
            $this->retryOrGiveUp($database, $job->id, $held, $message);
            $this->recordAttempt($database, $job->id, $held, 'failed', $message);
        });
    }

    /**
     * Returns jobs whose lease expired to the queue, or fails them when they used every attempt.
     *
     * @return int how many jobs were recovered
     */
    public function recoverExpired(int $limit = 100): int
    {
        return $this->database->transaction(function (JobDatabaseInterface $database) use ($limit): int {
            $expired = $database->select(self::EXPIRED, ['limit' => $limit]);
            foreach ($expired as $held) {
                $this->retryOrGiveUp($database, (int) $held['id'], $held, 'lease expired');
                $this->recordAttempt($database, (int) $held['id'], $held, 'lease_expired', null);
            }

            return \count($expired);
        }, IsolationLevelEnum::COMMITTED);
    }

    /** @param array<string, mixed> $payload */
    private static function payload(array $payload): string
    {
        return \json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /** Backoff before attempt $attempts + 1, seconds. */
    public static function backoff(int $attempts): int
    {
        return \min(self::MAX_BACKOFF, self::BASE_BACKOFF * 2 ** \max(0, $attempts - 1));
    }

    private function finish(LeasedJob $job, callable $operation): bool
    {
        return $this->database->transaction(function (JobDatabaseInterface $database) use ($job, $operation): bool {
            $held = $database->select(self::HELD, ['id' => $job->id, 'token' => $job->token])[0] ?? null;
            if ($held === null) {
                return false;
            }

            $operation($database, $held);

            return true;
        }, IsolationLevelEnum::COMMITTED);
    }

    /** @param array{attempts: mixed, maxAttempts: mixed} $held the jobs row as it is held */
    private function retryOrGiveUp(JobDatabaseInterface $database, int $id, array $held, string $error): void
    {
        $attempts = (int) $held['attempts'];
        if ($attempts >= (int) $held['maxAttempts']) {
            $database->query(self::GIVE_UP, ['id' => $id, 'error' => $error]);
            return;
        }

        $database->query(self::REQUEUE, ['id' => $id, 'error' => $error, 'delay' => self::backoff($attempts)]);
    }

    /** @param array{attempts: mixed, lockedBy: mixed, startedAt: mixed} $held */
    private function recordAttempt(JobDatabaseInterface $database, int $id, array $held, string $outcome,
        ?string $error): void
    {
        $database->query(self::ATTEMPT, ['id' => $id, 'attempt' => (int) $held['attempts'],
            'worker' => $held['lockedBy'], 'started' => $held['startedAt'], 'outcome' => $outcome,
            'error' => $error]);
    }
}
