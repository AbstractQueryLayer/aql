<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

interface MigrationOperationInterface
{
    public function executeMigrationOperation(): void;
}
