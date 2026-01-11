<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationInterface;

interface MigrationExecutorInterface
{
    /**
     * Execute migration with automatic rollback on failure.
     *
     * If any operation fails, automatically rolls back all previously executed operations
     * in reverse order and sets their status to 'rollback'. This ensures database consistency
     * even when migration fails mid-way through multiple operations.
     *
     * Example: If migration has 5 operations and operation #3 fails:
     * - Operations #1 and #2 are rolled back in reverse order (2 -> 1)
     * - Their status is set to 'rollback'
     * - Exception is thrown with failure details
     */
    public function executeMigration(MigrationInterface $migration): void;

    /**
     * Apply migration operations forward without automatic rollback.
     *
     * Executes all operations sequentially but does NOT automatically rollback
     * on failure. If an operation fails, previously executed operations remain
     * in 'completed' status. Use this when you want manual control over rollback
     * or when running migrations that should not be automatically reverted.
     *
     * Note: Failures still update the failed operation status to 'failed'.
     */
    public function applyMigration(MigrationInterface $migration): void;
}
