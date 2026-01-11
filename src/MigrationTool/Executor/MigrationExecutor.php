<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\Exceptions\MigrationExecutionException;
use IfCastle\AQL\MigrationTool\MigrationInterface;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\MigrationTool\MigrationStatus;
use IfCastle\AQL\MigrationTool\Repository\MigrationRepositoryInterface;
use IfCastle\Exceptions\CompositeException;

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
        $originalException = null;

        try {
            foreach ($migration->getMigrationOperations() as $operation) {
                $this->executeOperation($operation);
                $executedOperations[] = $operation;
            }
        } catch (\Throwable $e) {
            $originalException = $e;

            // Attempt to rollback all previously executed operations in reverse order
            try {
                $this->rollbackOperations(array_reverse($executedOperations));
            } catch (\Throwable $rollbackException) {
                // Rollback failed - throw composite exception with both errors
                throw new CompositeException(
                    "Migration failed and rollback encountered errors",
                    $originalException,
                    $rollbackException
                );
            }

            // Rollback succeeded - throw original exception
            throw $originalException;
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
     * Collects all exceptions during rollback and throws CompositeException if any occur.
     * Critical errors (\Error and subclasses) are re-thrown immediately.
     *
     * @param MigrationOperationInterface[] $operations
     * @throws CompositeException If any \Exception occurs during rollback
     * @throws \Error If critical error occurs (re-thrown immediately)
     */
    private function rollbackOperations(array $operations): void
    {
        $rollbackExceptions = [];

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
            } catch (\Error $error) {
                // Critical error (e.g., out of memory, fatal error) - re-throw immediately
                throw $error;
            } catch (\Exception $exception) {
                // Recoverable exception - collect and continue with other rollbacks
                $rollbackExceptions[] = $exception;
            }
        }

        // If any exceptions occurred during rollback, throw composite exception
        if (!empty($rollbackExceptions)) {
            throw new CompositeException(
                "Rollback failed for " . count($rollbackExceptions) . " operation(s)",
                ...$rollbackExceptions
            );
        }
    }
}
