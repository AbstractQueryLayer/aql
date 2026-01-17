<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

final class FileMigrationOperation implements MigrationOperationInterface
{
    private string $checksum;

    public function __construct(
        private readonly int $version,
        private readonly string $taskName,
        private readonly string $description,
        private readonly string $migrationDate,
        private readonly string $type,
        private readonly string $filePath,
        private readonly string $code,
        private readonly string $direction,
        private string $rollbackCode = ''
    ) {
        $this->checksum = $this->calculateChecksum();
    }

    #[\Override]
    public function getVersion(): int
    {
        return $this->version;
    }

    #[\Override]
    public function getTaskName(): string
    {
        return $this->taskName;
    }

    #[\Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    #[\Override]
    public function getMigrationDate(): string
    {
        return $this->migrationDate;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    #[\Override]
    public function getCode(): string
    {
        return $this->code;
    }

    #[\Override]
    public function getRollbackCode(): string
    {
        return $this->rollbackCode;
    }

    public function setRollbackCode(string $rollbackCode): void
    {
        $this->rollbackCode = $rollbackCode;
        $this->checksum = $this->calculateChecksum();
    }

    #[\Override]
    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function isUpMigration(): bool
    {
        return $this->direction === 'up' || $this->direction === 'both';
    }

    public function isDownMigration(): bool
    {
        return $this->direction === 'down';
    }

    #[\Override]
    public function executeMigrationOperation(): void
    {
        throw new MigrationException('File-based migration operation cannot be executed directly. Use MigrationExecutor.');
    }

    #[\Override]
    public function executeRollback(): void
    {
        throw new MigrationException('File-based migration operation cannot be rolled back directly. Use MigrationExecutor.');
    }

    private function calculateChecksum(): string
    {
        return hash('sha256', $this->code . $this->rollbackCode);
    }
}
