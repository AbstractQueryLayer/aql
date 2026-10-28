<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\Jobs\AqlJobDatabase;

/**
 * The tables of resources/mariadb.sql in the MariaDB test database, emptied for each test, and a job database on
 * them through AqlJobDatabase::standalone() with the package's own entities.
 */
final class JobTables
{
    public readonly \PDO $pdo;
    /** @var list<MySql> */
    private array $storages = [];

    public function __construct()
    {
        $this->pdo = MariaDb::pdo();
        $this->drop();
        $this->pdo->exec(\file_get_contents(__DIR__ . '/../../packages/jobs/resources/mariadb.sql'));
    }

    public function empty(): void
    {
        $this->pdo->exec('DELETE FROM job_attempts');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('ALTER TABLE jobs AUTO_INCREMENT = 1');
    }

    public function database(): AqlJobDatabase
    {
        $storage = new MySql(['dsn' => MariaDb::dsn(), 'username' => MariaDb::user(),
            'password' => MariaDb::password()]);
        $this->storages[] = $storage;

        return AqlJobDatabase::standalone($storage);
    }

    /** Closes the pools of database() and drops the tables. */
    public function drop(): void
    {
        foreach ($this->storages as $storage) {
            $storage->dispose();
        }

        $this->storages = [];
        $this->pdo->exec('DROP TABLE IF EXISTS job_attempts, jobs');
    }

    /** @return list<mixed> */
    public function row(int $id, string $columns): array
    {
        return $this->pdo->query('SELECT ' . $columns . ' FROM jobs WHERE id = ' . $id)->fetch(\PDO::FETCH_NUM);
    }

    /** @return list<list<?string>> attempt, worker, outcome, error of each attempt of the job */
    public function attempts(int $id): array
    {
        return $this->pdo->query('SELECT attempt, worker, outcome, error FROM job_attempts WHERE jobId = ' . $id
            . ' ORDER BY attempt')->fetchAll(\PDO::FETCH_NUM);
    }

    public function makeDue(int $id): void
    {
        $this->pdo->exec('UPDATE jobs SET runAfter = UTC_TIMESTAMP(6) WHERE id = ' . $id);
    }
}
