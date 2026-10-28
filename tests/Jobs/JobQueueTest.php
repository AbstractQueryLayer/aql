<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\Jobs\AqlJobDatabase;
use IfCastle\Jobs\JobDatabaseInterface;
use IfCastle\Jobs\JobQueue;
use IfCastle\Jobs\LeasedJob;
use PHPUnit\Framework\TestCase;

/** JobQueue on the package's tables and entities through AqlJobDatabase, on MariaDB. */
final class JobQueueTest extends TestCase
{
    private static ?JobTables $tables = null;
    private AqlJobDatabase $database;
    private JobQueue $queue;

    public static function setUpBeforeClass(): void
    {
        self::$tables = new JobTables();
    }

    public static function tearDownAfterClass(): void
    {
        self::$tables->drop();
        // The pools are closed; releasing the fixture closes its own connection too.
        self::$tables = null;
    }

    protected function setUp(): void
    {
        self::$tables->empty();
        $this->database = self::$tables->database();
        $this->queue = new JobQueue($this->database, 'w1');
    }

    public function testLeaseTakesTheDueJobWithAFreshTokenOnce(): void
    {
        $id = $this->queue->enqueue('mail.send', ['to' => 'ünïcode']);

        $job = $this->queue->lease();

        $this->assertNotNull($job);
        $this->assertSame([$id, 'mail.send', ['to' => 'ünïcode'], 1], [$job->id, $job->kind, $job->payload,
            $job->attempt]);
        $this->assertSame(16, \strlen($job->token));
        $this->assertSame(['running', 'w1', \strtoupper(\bin2hex($job->token))],
            self::$tables->row($id, 'state, lockedBy, HEX(leaseToken)'));
        $this->assertNull($this->queue->lease(), 'a running job is not leased again');
    }

    public function testLeaseTakesOnlyTheNamedKindsInPriorityOrder(): void
    {
        $late = $this->queue->enqueue('a', [], priority: 5);
        $other = $this->queue->enqueue('b', [], priority: 0);
        $first = $this->queue->enqueue('a', [], priority: 1);

        $this->assertSame($first, $this->queue->lease(['a'])?->id, 'a job of another kind ahead is skipped');
        $this->assertSame($late, $this->queue->lease(['a', 'c'])?->id);
        $this->assertNull($this->queue->lease(['a']));
        $this->assertNull($this->queue->lease([]), 'no kinds: nothing');
        $this->assertSame($other, $this->queue->lease()?->id, 'null: any kind');
    }

    public function testDedupKeyRefusesASecondActiveJobOnly(): void
    {
        $this->assertNotNull($this->queue->enqueue('a', [], 'key'));
        $this->assertNull($this->queue->enqueue('a', [], 'key'));

        $this->assertTrue($this->queue->complete($this->queue->lease()));

        $this->assertNotNull($this->queue->enqueue('a', [], 'key'), 'a finished job frees its key');
    }

    public function testDelayedAndScheduledJobsAreNotDue(): void
    {
        $delayed = $this->queue->enqueue('a', [], delaySeconds: 60);
        $this->queue->enqueue('a', [], runAt: '2999-01-01 00:00:00');

        $this->assertNull($this->queue->lease());
        $this->assertSame('2999-01-01 00:00:00.000000', $this->queue->runAfter($delayed + 1));
    }

    public function testRenewCompleteAndFailNeedTheLeaseToken(): void
    {
        $id = $this->queue->enqueue('a', []);
        $job = $this->queue->lease();
        $stranger = new LeasedJob($job->id, $job->kind, $job->payload, $job->attempt, \random_bytes(16));

        $this->assertFalse($this->queue->renew($stranger));
        $this->assertFalse($this->queue->complete($stranger));
        $this->assertFalse($this->queue->fail($stranger, new \RuntimeException()));
        $this->assertTrue($this->queue->renew($job));
        $this->assertTrue($this->queue->complete($job));

        $this->assertSame(['succeeded', null, null], self::$tables->row($id, 'state, lockedBy, leaseToken'));
        $this->assertSame([[1, 'w1', 'succeeded', null]], self::$tables->attempts($id));
        $this->assertFalse($this->queue->complete($job), 'a finished attempt is not finished twice');
    }

    public function testFailureRetriesAfterABackoffThenGivesUpWithAFixedErrorText(): void
    {
        $id = $this->queue->enqueue('a', [], 'key', maxAttempts: 2);

        $this->assertTrue($this->queue->fail($this->queue->lease(), new \RuntimeException('secret payload')));
        $this->assertSame(['queued', null], self::$tables->row($id, 'state, leaseToken'));
        $this->assertSame([(string) JobQueue::BASE_BACKOFF], self::$tables->row($id,
            'ROUND(TIMESTAMPDIFF(MICROSECOND, UTC_TIMESTAMP(6), runAfter) / 1000000)'));
        $this->assertNull($this->queue->lease(), 'the retry waits for its backoff');

        self::$tables->makeDue($id);
        $this->assertTrue($this->queue->fail($this->queue->lease(), new \LogicException('again')));

        $this->assertSame(['failed', null, 'LogicException: attempt failed'], self::$tables->row($id,
            'state, dedupKey, lastError'));
        $this->assertSame([[1, 'w1', 'failed', 'RuntimeException: attempt failed'],
            [2, 'w1', 'failed', 'LogicException: attempt failed']], self::$tables->attempts($id));
    }

    public function testBackoffDoublesUpToTheMaximum(): void
    {
        $this->assertSame([30, 60, 120, 3600, 3600], \array_map(JobQueue::backoff(...), [1, 2, 3, 8, 30]));
    }

    public function testExpiredLeaseReturnsTheJobAndAStaleWorkerCannotFinishIt(): void
    {
        $id = $this->queue->enqueue('a', [], maxAttempts: 2);
        $stale = $this->queue->lease();
        self::$tables->pdo->exec('UPDATE jobs SET lockedUntil = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND');

        $this->assertSame(1, $this->queue->recoverExpired());

        $this->assertSame(['queued', 'lease expired'], self::$tables->row($id, 'state, lastError'));
        $this->assertSame([[1, 'w1', 'lease_expired', null]], self::$tables->attempts($id));
        self::$tables->makeDue($id);
        $fresh = $this->queue->lease();
        $this->assertSame(2, $fresh->attempt);
        $this->assertFalse($this->queue->complete($stale), 'the old lease names another token');
        $this->assertTrue($this->queue->complete($fresh));
    }

    public function testExpiredLeaseOfTheLastAttemptFailsTheJob(): void
    {
        $id = $this->queue->enqueue('a', [], 'key', maxAttempts: 1);
        $this->queue->lease();
        self::$tables->pdo->exec('UPDATE jobs SET lockedUntil = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND');

        $this->assertSame(1, $this->queue->recoverExpired());

        $this->assertSame(['failed', null], self::$tables->row($id, 'state, dedupKey'));
    }

    public function testSubjectLookupsMoveAndCancel(): void
    {
        $first = $this->queue->enqueue('a', ['n' => 1], subjectKind: 'work', subjectId: 7, delaySeconds: 600);
        $second = $this->queue->enqueue('a', ['n' => 2], subjectKind: 'work', subjectId: 7, delaySeconds: 60);
        $this->queue->enqueue('a', [], subjectKind: 'work', subjectId: 8);

        $this->assertSame([$second, $first], \array_column($this->queue->queuedFor('a', 'work', 7), 'id'));

        $this->database->transaction(function (JobDatabaseInterface $database) use ($first): void {
            $queue = new JobQueue($database, 'w1');
            $this->assertSame([[$first, 'queued'], [$first + 1, 'queued']], \array_map(
                static fn(array $job): array => [$job['id'], $job['state']], $queue->lockActiveFor('a', 'work', 7)));
            $queue->move($first, '2000-01-01 00:00:00', ['n' => 3], 'moved');
        });

        $this->assertSame([['n' => 3], ['n' => 2]], \array_column($this->queue->queuedFor('a', 'work', 7), 'payload'));
        $this->assertSame(2, $this->queue->cancelFor('a', 'work', 7));
        $this->assertSame([], $this->queue->queuedFor('a', 'work', 7));
        $this->assertSame('cancelled', $this->queue->state($second));
        $this->assertNull($this->queue->state(999));
    }

    public function testEnqueueEveryWaitsForThePeriodAfterAFinishedJob(): void
    {
        $this->queue->enqueueEvery('sweep', 3600);
        $this->queue->enqueueEvery('sweep', 3600);
        $this->assertSame(1, (int) self::$tables->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
        $this->assertFalse($this->queue->running('sweep'));

        $job = $this->queue->lease();
        $this->assertTrue($this->queue->running('sweep'));
        $this->queue->complete($job);
        $this->queue->enqueueEvery('sweep', 3600);

        $this->assertTrue($this->queue->finishedWithin('sweep', 3600));
        $this->assertSame(1, (int) self::$tables->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testATransactionDoesNotNest(): void
    {
        $this->expectException(\LogicException::class);

        $this->database->transaction(static fn(JobDatabaseInterface $database) => $database->transaction(
            static fn() => null));
    }
}
