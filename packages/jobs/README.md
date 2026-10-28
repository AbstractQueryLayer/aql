# IfCastle Jobs

Persistent background jobs on AQL storage for the TrueAsync engine: a queue in two database tables, leases that
two workers never share, retries with exponential backoff, a log of every attempt, and a worker that runs a
handler per job kind.

## Installation

```bash
composer require ifcastle/jobs
```

## The schema contract

The queue speaks AQL to two entities, `Job` and `JobAttempt`. Create the tables from `resources/mariadb.sql`
(MariaDB 10.6 or later) and either register `IfCastle\Jobs\Entity` as an entity namespace or define entities of
your own with the same short names and at least the same properties; an application may add columns. Register
exactly one source of both entities: the first namespace holding a short name wins, silently.

Every time comes from the database clock, so workers on several hosts agree on due times and lease ends.

## Usage

```php
use IfCastle\Jobs\AqlJobDatabase;
use IfCastle\Jobs\JobKinds;
use IfCastle\Jobs\JobQueue;
use IfCastle\Jobs\LeasedJob;
use IfCastle\Jobs\WorkerCommand;

exit(WorkerCommand::run('Mail worker', static function (): array {
    $database = AqlJobDatabase::standalone(new MySql(['dsn' => $dsn, 'username' => $user, 'password' => $password]));
    $queue = new JobQueue($database, 'mail:' . \gethostname() . ':' . \getmypid());
    $kinds = (new JobKinds())
        ->handle('mail.send', static function (LeasedJob $job): void { /* send $job->payload */ })
        ->handle('mail.expire', static function (): void { /* … */ })
        ->every('mail.expire', 3600);

    return [$kinds->worker($queue)];
}));
```

Elsewhere, `$queue->enqueue('mail.send', ['to' => $address], dedupKey: 'mail:' . $id)` queues a job.

- A worker leases only the kinds it handles, so workers under different database accounts share one table.
- A handler throws to fail its attempt; the job is retried after 30 s, doubling up to an hour, until its
  `maxAttempts`, and each attempt leaves a `job_attempts` row with a fixed error text naming only the class.
- A lease is renewed every third of its length while the handler runs; a worker that lost its lease has its
  handler cancelled and cannot finish the attempt.
- `WorkerCommand` stops every worker between jobs on SIGTERM or SIGINT and returns 0, and returns 1 when a worker
  fails.
- An application with its own connection handling implements `JobDatabaseInterface` instead of using
  `AqlJobDatabase`.

## License

MIT License. See LICENSE file for details.
