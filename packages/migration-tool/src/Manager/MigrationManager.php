<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Manager;

use IfCastle\AQL\MigrationTool\Executor\MigrationExecutorInterface;
use IfCastle\AQL\MigrationTool\MigrationInterface;
use IfCastle\AQL\MigrationTool\Repository\MigrationRepositoryInterface;
use IfCastle\AQL\MigrationTool\Repository\MigrationSourceRepositoryInterface;

final readonly class MigrationManager implements MigrationManagerInterface
{
    public function __construct(
        private MigrationSourceRepositoryInterface $sourceRepository,
        private MigrationRepositoryInterface $repository,
        private MigrationExecutorInterface $executor
    ) {}

    #[\Override]
    public function migrate(): array
    {
        $pendingMigrations = $this->getPendingMigrations();

        foreach ($pendingMigrations as $migration) {
            // Save pending operations to repository before execution
            foreach ($migration->getMigrationOperations() as $operation) {
                if (!$this->repository->isExecuted($operation->getVersion(), $operation->getTaskName())) {
                    $this->repository->save($operation);
                }
            }

            // Execute migration
            $this->executor->executeMigration($migration);
        }

        return $pendingMigrations;
    }

    #[\Override]
    public function rollback(int $steps = 1): void
    {
        $executedOperations = $this->repository->getAllExecuted();
        $toRollback = array_slice(array_reverse($executedOperations), 0, $steps);

        foreach ($toRollback as $operation) {
            $operation->executeRollback();
        }
    }

    #[\Override]
    public function getPendingMigrations(): array
    {
        $lastExecuted = $this->repository->getLastExecuted();
        $fromDate = $lastExecuted?->getMigrationDate() ?? '1970-01-01';

        $allMigrations = $this->sourceRepository->scanFromDate($fromDate);
        $pending = [];

        foreach ($allMigrations as $migration) {
            $hasPendingOperations = false;

            foreach ($migration->getMigrationOperations() as $operation) {
                if (!$this->repository->isExecuted($operation->getVersion(), $operation->getTaskName())) {
                    $hasPendingOperations = true;
                    break;
                }
            }

            if ($hasPendingOperations) {
                $pending[] = $migration;
            }
        }

        return $pending;
    }

    #[\Override]
    public function getStatus(): array
    {
        $allMigrations = $this->sourceRepository->loadAll();
        $executed = [];
        $pending = [];

        foreach ($allMigrations as $migration) {
            $isFullyExecuted = true;

            foreach ($migration->getMigrationOperations() as $operation) {
                if (!$this->repository->isExecuted($operation->getVersion(), $operation->getTaskName())) {
                    $isFullyExecuted = false;
                    break;
                }
            }

            if ($isFullyExecuted) {
                $executed[] = $migration;
            } else {
                $pending[] = $migration;
            }
        }

        return [
            'executed' => $executed,
            'pending' => $pending,
        ];
    }
}
