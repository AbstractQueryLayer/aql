<?php

declare(strict_types=1);

// Two idle workers under WorkerCommand; prints "ready" once the command runs, then exits with its status.
// Usage: php idle-worker.php

use IfCastle\AQL\Jobs\IdleDatabase;
use IfCastle\Jobs\JobQueue;
use IfCastle\Jobs\JobWorker;
use IfCastle\Jobs\WorkerCommand;

require_once __DIR__ . '/../../../vendor/autoload.php';

Async\spawn(static function (): void {
    Async\delay(100);
    echo "ready\n";
});

exit(WorkerCommand::run('Idle worker', static fn(): array => [
    new JobWorker(new JobQueue(new IdleDatabase(), 'a'), ['a' => static fn() => null]),
    new JobWorker(new JobQueue(new IdleDatabase(), 'b'), ['b' => static fn() => null]),
], 10));
