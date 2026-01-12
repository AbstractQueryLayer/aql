<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\DTO\DataTransferObjectAbstract;
use IfCastle\AQL\DTO\DtoMap;
use IfCastle\AQL\DTO\Map;
use IfCastle\AQL\MigrationTool\MigrationStatus;

#[DtoMap('MigrationEntity')]
class MigrationDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map]
        public int $version,
        #[Map]
        public string $taskName,
        #[Map]
        public string $description,
        #[Map]
        public string $migrationDate,
        #[Map]
        public string $type,
        #[Map]
        public string $filePath,
        #[Map]
        public string $code,
        #[Map]
        public string $rollbackCode,
        #[Map]
        public string $checksum,
        #[Map]
        public string $status = MigrationStatus::PENDING->value,
        #[Map]
        public ?\DateTimeImmutable $startedAt = null,
        #[Map]
        public ?\DateTimeImmutable $completedAt = null,
        #[Map]
        public ?string $errorData = null,
        #[Map(isPrimaryKey: true, isHidden: true)]
        public int $id = 0
    ) {}
}
