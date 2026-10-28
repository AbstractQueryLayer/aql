<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\Jobs\JobQueue;
use IfCastle\Jobs\JobWorker;
use IfCastle\Jobs\WorkerCommand;
use PHPUnit\Framework\TestCase;

/** The worker process body: its exit status and its one line on failure, without a database. */
final class WorkerCommandTest extends TestCase
{
    /** @var resource */
    private mixed $errors;

    protected function setUp(): void
    {
        $this->errors = \fopen('php://memory', 'w+');
    }

    public function testAFactoryErrorExitsOneNamingOnlyItsClass(): void
    {
        $status = WorkerCommand::run('Job worker', static fn() => throw new \RuntimeException('password=secret'),
            10, $this->errors);

        $this->assertSame([1, "Job worker stopped: RuntimeException\n"], [$status, $this->written()]);
    }

    public function testAFailingWorkerStopsTheOthers(): void
    {
        $idle = new JobWorker(new JobQueue(new IdleDatabase(), 'idle'), ['a' => static fn() => null]);
        $failing = new JobWorker(new JobQueue(new IdleDatabase(new \DomainException('dsn with password')), 'bad'),
            ['b' => static fn() => null]);

        $status = WorkerCommand::run('Maintenance worker', static fn() => [$idle, $failing], 10, $this->errors);

        $this->assertSame([1, "Maintenance worker stopped: DomainException\n"], [$status, $this->written()]);
    }

    public function testSigtermStopsTheWorkersAndExitsZero(): void
    {
        $process = \proc_open([\PHP_BINARY, __DIR__ . '/Fixtures/idle-worker.php'], [1 => ['pipe', 'w'],
            2 => ['pipe', 'w']], $pipes);
        $this->assertSame("ready\n", \fgets($pipes[1]));

        \proc_terminate($process, \SIGTERM);
        $errors = \stream_get_contents($pipes[2]);

        $this->assertSame([0, ''], [\proc_close($process), $errors]);
    }

    private function written(): string
    {
        \rewind($this->errors);

        return \stream_get_contents($this->errors);
    }
}
