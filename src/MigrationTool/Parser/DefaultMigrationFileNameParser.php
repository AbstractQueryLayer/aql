<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Parser;

final class DefaultMigrationFileNameParser implements MigrationFileNameParserInterface
{
    /**
     * Pattern for SQL files: 0001-TASK-123-description_up.sql or 0001-TASK-123-description_down.sql
     * Pattern for PHP files: 0001-TASK-123-description.php
     */
    private const string SQL_PATTERN = '/^(\d+)-([A-Z]+-\d+)-(.+)_(up|down)\.(sql)$/';
    private const string PHP_PATTERN = '/^(\d+)-([A-Z]+-\d+)-(.+)\.(php)$/';

    #[\Override]
    public function parse(string $fileName): MigrationMetadata
    {
        // Try SQL pattern first
        if (preg_match(self::SQL_PATTERN, $fileName, $matches)) {
            return new MigrationMetadata(
                version: (int)$matches[1],
                taskName: $matches[2],
                description: $matches[3],
                type: 'sql',
                direction: $matches[4],
                extension: $matches[5]
            );
        }

        // Try PHP pattern
        if (preg_match(self::PHP_PATTERN, $fileName, $matches)) {
            return new MigrationMetadata(
                version: (int)$matches[1],
                taskName: $matches[2],
                description: $matches[3],
                type: 'php',
                direction: 'both',
                extension: $matches[4]
            );
        }

        throw new \InvalidArgumentException("Invalid migration file name format: {$fileName}");
    }

    #[\Override]
    public function isValidFileName(string $fileName): bool
    {
        return preg_match(self::SQL_PATTERN, $fileName) === 1
            || preg_match(self::PHP_PATTERN, $fileName) === 1;
    }
}
