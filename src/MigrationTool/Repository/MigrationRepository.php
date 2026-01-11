<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\MigrationTool\Exceptions\MigrationOperationNotExecutable;
use IfCastle\AQL\MigrationTool\Repository\MigrationEntity;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\MigrationTool\MigrationStatus;
use IfCastle\AQL\Storage\StorageInterface;

final class MigrationRepository implements MigrationRepositoryInterface
{
    public function __construct(
        private readonly StorageInterface $storage
    ) {}

    #[\Override]
    public function getLastExecuted(): ?MigrationOperationInterface
    {
        $result = $this->storage
            ->from(MigrationEntity::entity())
            ->where('status', MigrationStatus::COMPLETED->value)
            ->orderBy('version', 'DESC')
            ->limit(1)
            ->fetchOne();

        return $result ? $this->hydrateOperation($result) : null;
    }

    #[\Override]
    public function getByTaskName(string $taskName): array
    {
        $results = $this->storage
            ->from(MigrationEntity::entity())
            ->where('taskName', $taskName)
            ->orderBy('version', 'ASC')
            ->fetchAll();

        return array_map(fn($row) => $this->hydrateOperation($row), $results);
    }

    #[\Override]
    public function isExecuted(int $version, string $taskName): bool
    {
        $result = $this->storage
            ->from(MigrationEntity::entity())
            ->where('version', $version)
            ->where('taskName', $taskName)
            ->where('status', MigrationStatus::COMPLETED->value)
            ->fetchOne();

        return $result !== null;
    }

    #[\Override]
    public function save(MigrationOperationInterface $operation): void
    {
        $this->storage
            ->into(MigrationEntity::entity())
            ->insert([
                'version' => $operation->getVersion(),
                'taskName' => $operation->getTaskName(),
                'description' => $operation->getDescription(),
                'migrationDate' => $operation->getMigrationDate(),
                'type' => $operation->getType(),
                'filePath' => $operation->getFilePath(),
                'code' => $operation->getCode(),
                'rollbackCode' => $operation->getRollbackCode(),
                'checksum' => $operation->getChecksum(),
                'status' => MigrationStatus::PENDING->value,
                'startedAt' => null,
                'completedAt' => null,
            ]);
    }

    #[\Override]
    public function updateStatus(
        int $version,
        string $taskName,
        string $status,
        ?\DateTimeInterface $startedAt = null,
        ?\DateTimeInterface $completedAt = null
    ): void {
        $updateData = ['status' => $status];

        if ($startedAt !== null) {
            $updateData['startedAt'] = $startedAt;
        }

        if ($completedAt !== null) {
            $updateData['completedAt'] = $completedAt;
        }

        $this->storage
            ->update(MigrationEntity::entity())
            ->set($updateData)
            ->where('version', $version)
            ->where('taskName', $taskName)
            ->execute();
    }

    #[\Override]
    public function getAllExecuted(): array
    {
        $results = $this->storage
            ->from(MigrationEntity::entity())
            ->where('status', MigrationStatus::COMPLETED->value)
            ->orderBy('version', 'ASC')
            ->fetchAll();

        return array_map(fn($row) => $this->hydrateOperation($row), $results);
    }

    private function hydrateOperation(array $row): MigrationOperationInterface
    {
        return new class($row) implements MigrationOperationInterface {
            public function __construct(private readonly array $data) {}

            public function getVersion(): int
            {
                return (int)$this->data['version'];
            }

            public function getTaskName(): string
            {
                return $this->data['taskName'];
            }

            public function getDescription(): string
            {
                return $this->data['description'];
            }

            public function getMigrationDate(): string
            {
                return $this->data['migrationDate'];
            }

            public function getType(): string
            {
                return $this->data['type'];
            }

            public function getFilePath(): string
            {
                return $this->data['filePath'];
            }

            public function getCode(): string
            {
                return $this->data['code'];
            }

            public function getRollbackCode(): string
            {
                return $this->data['rollbackCode'] ?? '';
            }

            public function getChecksum(): string
            {
                return $this->data['checksum'];
            }

            public function executeMigrationOperation(): void
            {
                throw new MigrationOperationNotExecutable('Cannot execute operation loaded from database. Use MigrationExecutor.');
            }

            public function executeRollback(): void
            {
                throw new MigrationOperationNotExecutable('Cannot rollback operation loaded from database. Use MigrationExecutor.');
            }
        };
    }
}
