<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\Jobs\AqlJobDatabase;
use IfCastle\Jobs\JobKinds;
use IfCastle\Jobs\JobQueue;
use IfCastle\Jobs\JobWorker;
use IfCastle\Jobs\LeasedJob;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/** JobWorker and the workers JobKinds builds, on MariaDB. */
final class JobWorkerTest extends TestCase
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

    public function testWorkerRunsTheHandlerOfAScheduledKindAndCompletesIt(): void
    {
        $ran = [];
        $worker = (new JobKinds())->handle('sweep', static function (LeasedJob $job) use (&$ran): void {
            $ran[] = [$job->kind, $job->attempt];
        })->every('sweep', 3600)->worker($this->queue);

        $this->assertTrue($worker->runOnce());
        $this->assertFalse($worker->runOnce(), 'the period has not passed');

        $this->assertSame([['sweep', 1]], $ran);
        $this->assertSame([[1, 'w1', 'succeeded', null]], self::$tables->attempts(1));
    }

    public function testWorkerFailsAThrowingHandlerAndLeavesOtherKindsQueued(): void
    {
        $failing = $this->queue->enqueue('a', [], priority: 0);
        $other = $this->queue->enqueue('b', [], priority: 1);
        $worker = (new JobKinds())->handle('a', static function (): void {
            throw new \DomainException('payload text');
        })->worker($this->queue);

        $this->assertTrue($worker->runOnce());
        $this->assertFalse($worker->runOnce(), 'the failed job waits for its backoff; the other kind is not taken');

        $this->assertSame('DomainException: attempt failed', self::$tables->attempts($failing)[0][3]);
        $this->assertSame(['queued', '0'], \array_map('strval', self::$tables->row($other, 'state, attempts')));
    }

    public function testLostLeaseCancelsTheHandlerAtTheFirstRenewalOfTheQueuesLease(): void
    {
        $queue = new JobQueue($this->database, 'w1', 3);
        $id = $queue->enqueue('a', []);
        $steps = [];
        $worker = (new JobKinds())->handle('a', static function () use (&$steps): void {
            $steps[] = 'started';
            self::$tables->pdo->exec('UPDATE jobs SET leaseToken = RANDOM_BYTES(16)');
            delay(5000);
            $steps[] = 'wrote after the lease was lost';
        })->worker($queue);
        $started = \hrtime(true);

        $this->assertTrue($worker->runOnce());

        $this->assertSame(['started'], $steps);
        $this->assertLessThan(3.0, (\hrtime(true) - $started) / 1e9,
            'renewed every second, a third of the queue\'s 3 s lease, not of a default 120 s');
        $this->assertSame([], self::$tables->attempts($id), 'the attempt is not this worker\'s to record');
    }

    public function testRunUntilReturnsTheFinalStateOfItsJob(): void
    {
        $id = $this->queue->enqueue('a', [], maxAttempts: 1);
        $worker = new JobWorker($this->queue, ['a' => static function (): void {
            throw new \RuntimeException();
        }]);

        $this->assertSame('failed', $worker->runUntil(10, $id));
    }

    public function testStopEndsTheLoopAfterTheRunningJobFinishes(): void
    {
        $this->queue->enqueue('a', []);
        $second = $this->queue->enqueue('a', []);
        $worker = null;
        $worker = new JobWorker($this->queue, ['a' => static function () use (&$worker): void {
            $worker->stop();
            delay(50);
        }]);

        $this->assertSame(JobWorker::STOPPED, await(spawn($worker->runUntil(...), 10)));

        $this->assertSame([[1, 'w1', 'succeeded', null]], self::$tables->attempts(1), 'the running job finished');
        $this->assertSame(['queued'], self::$tables->row($second, 'state'), 'no job after the stop');
    }
}
