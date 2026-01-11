<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

interface MigrationOperationInterface
{
    public function getVersion(): int;

    public function getTaskName(): string;

    public function getDescription(): string;

    public function getMigrationDate(): string;

    public function getType(): string;

    public function getFilePath(): string;

    public function getCode(): string;

    public function getRollbackCode(): string;

    public function getChecksum(): string;

    public function executeMigrationOperation(): void;

    public function executeRollback(): void;
}
