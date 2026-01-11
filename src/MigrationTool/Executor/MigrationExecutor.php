<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationInterface;
use IfCastle\AQL\MigrationTool\MigrationStatus;
use IfCastle\AQL\MigrationTool\Repository\MigrationRepositoryInterface;

final class MigrationExecutor implements MigrationExecutorInterface
{
    /**
     * @param MigrationOperationExecutorInterface[] $executors
     */
    public function __construct(
        private readonly MigrationRepositoryInterface $repository,
        private readonly array $executors
    ) {}

    #[\Override]
    public function executeMigration(MigrationInterface $migration): void
    {
        foreach ($migration->getMigrationOperations() as $operation) {
            $executor = $this->findExecutor($operation);

            if ($executor === null) {
                throw new \RuntimeException(
                    "No executor found for migration type: {$operation->getType()}"
                );
            }

            // Update status to running
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

                throw new \RuntimeException(
                    "Migration failed: {$operation->getTaskName()} v{$operation->getVersion()}: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }

    private function findExecutor($operation): ?MigrationOperationExecutorInterface
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($operation)) {
                return $executor;
            }
        }

        return null;
    }
}
