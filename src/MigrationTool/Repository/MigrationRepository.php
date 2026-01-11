<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\MigrationTool\MigrationStatus;
use IfCastle\Exceptions\BaseException;

final class MigrationRepository implements MigrationRepositoryInterface
{
    public function __construct(
        private readonly AqlExecutorInterface $aqlExecutor
    ) {}

    #[\Override]
    public function getLastExecuted(): ?MigrationOperationInterface
    {
        $dto = MigrationDto::fetchOne(
            $this->aqlExecutor,
            Select::from(MigrationEntity::entity())
                ->where('status', MigrationStatus::COMPLETED->value)
                ->orderBy('version', 'DESC')
                ->limit(1)
        );

        return $dto ? $this->dtoToOperation($dto) : null;
    }

    #[\Override]
    public function getByTaskName(string $taskName): array
    {
        $dtos = MigrationDto::fetch(
            $this->aqlExecutor,
            Select::from(MigrationEntity::entity())
                ->where('taskName', $taskName)
                ->orderBy('version', 'ASC')
        );

        return array_map(fn($dto) => $this->dtoToOperation($dto), $dtos);
    }

    #[\Override]
    public function isExecuted(int $version, string $taskName): bool
    {
        $dto = MigrationDto::fetchOne(
            $this->aqlExecutor,
            Select::from(MigrationEntity::entity())
                ->where('version', $version)
                ->where('taskName', $taskName)
                ->where('status', MigrationStatus::COMPLETED->value)
        );

        return $dto !== null;
    }

    #[\Override]
    public function save(MigrationOperationInterface $operation): void
    {
        $dto = new MigrationDto(
            version: $operation->getVersion(),
            taskName: $operation->getTaskName(),
            description: $operation->getDescription(),
            migrationDate: $operation->getMigrationDate(),
            type: $operation->getType(),
            filePath: $operation->getFilePath(),
            code: $operation->getCode(),
            rollbackCode: $operation->getRollbackCode(),
            checksum: $operation->getChecksum(),
            status: MigrationStatus::PENDING->value,
            startedAt: null,
            completedAt: null,
            errorData: null
        );

        $dto->insert($this->aqlExecutor);
    }

    #[\Override]
    public function updateStatus(
        int $version,
        string $taskName,
        string $status,
        ?\DateTimeInterface $startedAt = null,
        ?\DateTimeInterface $completedAt = null,
        ?\Throwable $error = null
    ): void {
        // Fetch existing DTO
        $dto = MigrationDto::fetchOne(
            $this->aqlExecutor,
            Select::from(MigrationEntity::entity())
                ->where('version', $version)
                ->where('taskName', $taskName)
        );

        if ($dto === null) {
            throw new MigrationException("Migration operation not found: version={$version}, taskName={$taskName}");
        }

        // Update fields
        $dto->status = $status;

        if ($startedAt !== null) {
            $dto->startedAt = $startedAt instanceof \DateTimeImmutable
                ? $startedAt
                : \DateTimeImmutable::createFromInterface($startedAt);
        }

        if ($completedAt !== null) {
            $dto->completedAt = $completedAt instanceof \DateTimeImmutable
                ? $completedAt
                : \DateTimeImmutable::createFromInterface($completedAt);
        }

        if ($error !== null) {
            $dto->errorData = json_encode(BaseException::serializeToArray($error));
        }

        $dto->update($this->aqlExecutor);
    }

    #[\Override]
    public function getAllExecuted(): array
    {
        $dtos = MigrationDto::fetch(
            $this->aqlExecutor,
            Select::from(MigrationEntity::entity())
                ->where('status', MigrationStatus::COMPLETED->value)
                ->orderBy('version', 'ASC')
        );

        return array_map(fn($dto) => $this->dtoToOperation($dto), $dtos);
    }

    private function dtoToOperation(MigrationDto $dto): MigrationOperationInterface
    {
        return new class($dto) implements MigrationOperationInterface {
            public function __construct(private readonly MigrationDto $dto) {}

            public function getVersion(): int
            {
                return $this->dto->version;
            }

            public function getTaskName(): string
            {
                return $this->dto->taskName;
            }

            public function getDescription(): string
            {
                return $this->dto->description;
            }

            public function getMigrationDate(): string
            {
                return $this->dto->migrationDate;
            }

            public function getType(): string
            {
                return $this->dto->type;
            }

            public function getFilePath(): string
            {
                return $this->dto->filePath;
            }

            public function getCode(): string
            {
                return $this->dto->code;
            }

            public function getRollbackCode(): string
            {
                return $this->dto->rollbackCode;
            }

            public function getChecksum(): string
            {
                return $this->dto->checksum;
            }

            public function executeMigrationOperation(): void
            {
                throw new MigrationException('Cannot execute operation loaded from database. Use MigrationExecutor.');
            }

            public function executeRollback(): void
            {
                throw new MigrationException('Cannot rollback operation loaded from database. Use MigrationExecutor.');
            }
        };
    }
}
