<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\MigrationTool\MigrationInterface;

interface MigrationSourceRepositoryInterface
{
    /**
     * Scan and load migrations from source directory starting from specified date.
     *
     * @param string $fromDate Date in format YYYY-MM-DD (folder name)
     * @return MigrationInterface[] Array of migrations grouped by task name
     */
    public function scanFromDate(string $fromDate): array;

    /**
     * Load all available migrations from source.
     *
     * @return MigrationInterface[] Array of migrations grouped by task name
     */
    public function loadAll(): array;

    /**
     * Get base directory path where migration files are stored.
     */
    public function getBasePath(): string;
}
