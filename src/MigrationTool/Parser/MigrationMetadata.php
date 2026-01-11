<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Parser;

final readonly class MigrationMetadata
{
    public function __construct(
        public int $version,
        public string $taskName,
        public string $description,
        public string $type,
        public string $direction,
        public string $extension
    ) {}

    public function isUpMigration(): bool
    {
        return $this->direction === 'up';
    }

    public function isDownMigration(): bool
    {
        return $this->direction === 'down';
    }
}
