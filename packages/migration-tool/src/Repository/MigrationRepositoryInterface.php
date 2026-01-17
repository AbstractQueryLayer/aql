<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

interface MigrationRepositoryInterface
{
    /**
     * Get the last executed migration operation.
     */
    public function getLastExecuted(): ?MigrationOperationInterface;

    /**
     * Get all executed migration operations for a specific task.
     *
     * @return MigrationOperationInterface[]
     */
    public function getByTaskName(string $taskName): array;

    /**
     * Check if migration operation was already executed.
     */
    public function isExecuted(int $version, string $taskName): bool;

    /**
     * Save migration operation to storage.
     */
    public function save(MigrationOperationInterface $operation): void;

    /**
     * Update migration operation status.
     */
    public function updateStatus(
        int $version,
        string $taskName,
        string $status,
        ?\DateTimeInterface $startedAt = null,
        ?\DateTimeInterface $completedAt = null,
        ?\Throwable $error = null
    ): void;

    /**
     * Get all executed operations ordered by version.
     *
     * @return MigrationOperationInterface[]
     */
    public function getAllExecuted(): array;
}
