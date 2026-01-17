<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Manager;

use IfCastle\AQL\MigrationTool\MigrationInterface;

interface MigrationManagerInterface
{
    /**
     * Execute pending migrations.
     *
     * @return MigrationInterface[] Executed migrations
     */
    public function migrate(): array;

    /**
     * Rollback last migration.
     */
    public function rollback(int $steps = 1): void;

    /**
     * Get pending migrations that haven't been executed yet.
     *
     * @return MigrationInterface[]
     */
    public function getPendingMigrations(): array;

    /**
     * Get status of all migrations.
     *
     * @return array{executed: MigrationInterface[], pending: MigrationInterface[]}
     */
    public function getStatus(): array;
}
