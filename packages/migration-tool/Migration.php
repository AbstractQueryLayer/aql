<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

class Migration implements MigrationInterface
{
    public function __construct(
        protected string $migrationName,
        protected string $migrationDescription = '',
        protected array $migrationOperations = []
    ) {}

    #[\Override]
    public function getMigrationName(): string
    {
        return $this->migrationName;
    }

    #[\Override]
    public function getMigrationDescription(): string
    {
        return $this->migrationDescription;
    }

    #[\Override]
    public function getMigrationOperations(): array
    {
        return $this->migrationOperations;
    }

    #[\Override] public function addMigrationOperation(MigrationOperationInterface $migrationOperation): static
    {
        $this->migrationOperations[] = $migrationOperation;
        return $this;
    }
}
