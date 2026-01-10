<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationInterface;

interface MigrationExecutorInterface
{
    public function executeMigration(MigrationInterface $migration): void;
}
