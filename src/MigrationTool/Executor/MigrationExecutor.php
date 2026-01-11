<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\Exceptions\MigrationExecutionException;
use IfCastle\AQL\MigrationTool\MigrationInterface;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\MigrationTool\MigrationStatus;
use IfCastle\AQL\MigrationTool\Repository\MigrationRepositoryInterface;

final readonly class MigrationExecutor implements MigrationExecutorInterface
{
    /**
     * @param MigrationOperationExecutorInterface[] $executors
     */
    public function __construct(
        private MigrationRepositoryInterface $repository,
        private array                        $executors
    ) {}

    #[\Override]
    public function executeMigration(MigrationInterface $migration): void
    {
        $executedOperations = [];

        try {
            foreach ($migration->getMigrationOperations() as $operation) {
                $this->executeOperation($operation);
                $executedOperations[] = $operation;
            }
        } catch (\Throwable $e) {
            // Rollback all previously executed operations in reverse order
            $this->rollbackOperations(array_reverse($executedOperations));
            throw $e;
        }
    }

    #[\Override]
    public function applyMigration(MigrationInterface $migration): void
    {
        foreach ($migration->getMigrationOperations() as $operation) {
            $this->executeOperation($operation);
        }
    }

    private function executeOperation(MigrationOperationInterface $operation): void
    {
        $executor = array_find(
            $this->executors,
            static fn(MigrationOperationExecutorInterface $executor) => $executor->supports($operation)
        );

        if ($executor === null) {
            throw new MigrationException("No executor found for migration type: {$operation->getType()}");
        }

        $this->repository->updateStatus(
            $operation->getVersion(),
            $operation->getTaskName(),
            MigrationStatus::RUNNING->value,
            new \DateTime()
        );

        try {
            // Execute the migration operation
            $executor->execute($operation);

            // Update status to completed
            $this->repository->updateStatus(
                $operation->getVersion(),
                $operation->getTaskName(),
                MigrationStatus::COMPLETED->value,
                null,
                new \DateTime()
            );
        } catch (\Throwable $e) {
            // Update status to failed
            $this->repository->updateStatus(
                $operation->getVersion(),
                $operation->getTaskName(),
                MigrationStatus::FAILED->value
            );

            throw new MigrationExecutionException(
                $operation->getTaskName(),
                $operation->getVersion(),
                $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Rollback operations in reverse order.
     *
     * @param MigrationOperationInterface[] $operations
     */
    private function rollbackOperations(array $operations): void
    {
        foreach ($operations as $operation) {
            $executor = array_find(
                $this->executors,
                static fn(MigrationOperationExecutorInterface $executor) => $executor->supports($operation)
            );

            if ($executor === null) {
                continue; // Skip if no executor found
            }

            try {
                $executor->rollback($operation);

                // Update status to rollback
                $this->repository->updateStatus(
                    $operation->getVersion(),
                    $operation->getTaskName(),
                    MigrationStatus::ROLLBACK->value
                );
            } catch (\Throwable $rollbackException) {
                // Log rollback failure but continue with other rollbacks
                // In production, this should be logged properly
            }
        }
    }
}
