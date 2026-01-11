<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

interface MigrationOperationExecutorInterface
{
    /**
     * Execute migration operation.
     */
    public function execute(MigrationOperationInterface $operation): void;

    /**
     * Rollback migration operation.
     */
    public function rollback(MigrationOperationInterface $operation): void;

    /**
     * Check if this executor supports given operation.
     */
    public function supports(MigrationOperationInterface $operation): bool;
}
