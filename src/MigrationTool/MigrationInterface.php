<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

interface MigrationInterface
{
    public function getMigrationName(): string;

    public function getMigrationDescription(): string;

    /**
     * @return MigrationOperationInterface[]
     */
    public function getMigrationOperations(): array;

    public function addMigrationOperation(MigrationOperationInterface $migrationOperation): static;
}
