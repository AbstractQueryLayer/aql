<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Parser;

interface MigrationFileNameParserInterface
{
    /**
     * Parse migration file name and extract metadata.
     *
     * @param string $fileName File name to parse (e.g., "0001-TASK-123-add-users-table_up.sql")
     * @return MigrationMetadata Parsed metadata
     * @throws \InvalidArgumentException If file name format is invalid
     */
    public function parse(string $fileName): MigrationMetadata;

    /**
     * Check if file name matches expected format.
     */
    public function isValidFileName(string $fileName): bool;
}
