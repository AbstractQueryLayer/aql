<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationInterface;

class LiveMigrationExecutor implements MigrationExecutorInterface
{
    #[\Override]
    public function executeMigration(MigrationInterface $migration): void
    {
        foreach ($migration->getMigrationOperations() as $operation) {
            $operation->executeMigrationOperation();
        }
    }
}
