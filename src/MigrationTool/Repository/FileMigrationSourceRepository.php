<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Repository;

use IfCastle\AQL\MigrationTool\Exceptions\InvalidMigrationFile;
use IfCastle\AQL\MigrationTool\Exceptions\MigrationPathNotFound;
use IfCastle\AQL\MigrationTool\Migration;
use IfCastle\AQL\MigrationTool\MigrationInterface;
use IfCastle\AQL\MigrationTool\Parser\MigrationFileNameParserInterface;
use IfCastle\AQL\MigrationTool\Repository\FileMigrationOperation;

final class FileMigrationSourceRepository implements MigrationSourceRepositoryInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly MigrationFileNameParserInterface $fileNameParser
    ) {
        if (!is_dir($this->basePath)) {
            throw new MigrationPathNotFound($this->basePath);
        }
    }

    #[\Override]
    public function scanFromDate(string $fromDate): array
    {
        $folders = $this->getFoldersFromDate($fromDate);
        return $this->loadMigrationsFromFolders($folders);
    }

    #[\Override]
    public function loadAll(): array
    {
        $folders = $this->getAllFolders();
        return $this->loadMigrationsFromFolders($folders);
    }

    #[\Override]
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get all date folders sorted ascending.
     *
     * @return string[]
     */
    private function getAllFolders(): array
    {
        $folders = [];
        $iterator = new \DirectoryIterator($this->basePath);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot() && $this->isValidDateFolder($fileInfo->getFilename())) {
                $folders[] = $fileInfo->getFilename();
            }
        }

        sort($folders);
        return $folders;
    }

    /**
     * Get date folders starting from specified date.
     *
     * @return string[]
     */
    private function getFoldersFromDate(string $fromDate): array
    {
        $allFolders = $this->getAllFolders();
        return array_filter($allFolders, static fn($folder) => $folder >= $fromDate);
    }

    /**
     * Check if folder name is valid date format (YYYY-MM-DD).
     */
    private function isValidDateFolder(string $folderName): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $folderName) === 1;
    }

    /**
     * Load migrations from specified folders.
     *
     * @param string[] $folders
     * @return MigrationInterface[]
     */
    private function loadMigrationsFromFolders(array $folders): array
    {
        $operations = [];

        foreach ($folders as $folder) {
            $folderPath = $this->basePath . DIRECTORY_SEPARATOR . $folder;
            $operations = array_merge($operations, $this->loadOperationsFromFolder($folderPath, $folder));
        }

        return $this->groupOperationsByTaskName($operations);
    }

    /**
     * Load migration operations from a single folder.
     *
     * @return FileMigrationOperation[]
     */
    private function loadOperationsFromFolder(string $folderPath, string $migrationDate): array
    {
        $operations = [];
        $iterator = new \DirectoryIterator($folderPath);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $this->fileNameParser->isValidFileName($fileInfo->getFilename())) {
                $metadata = $this->fileNameParser->parse($fileInfo->getFilename());
                $filePath = $fileInfo->getPathname();
                $code = file_get_contents($filePath);

                if ($code === false) {
                    throw new InvalidMigrationFile($filePath, 'Failed to read file contents');
                }

                $operations[] = new FileMigrationOperation(
                    version: $metadata->version,
                    taskName: $metadata->taskName,
                    description: $metadata->description,
                    migrationDate: $migrationDate,
                    type: $metadata->type,
                    filePath: $filePath,
                    code: $code,
                    direction: $metadata->direction
                );
            }
        }

        return $operations;
    }

    /**
     * Group operations by task name into Migration objects.
     *
     * @param FileMigrationOperation[] $operations
     * @return MigrationInterface[]
     */
    private function groupOperationsByTaskName(array $operations): array
    {
        $grouped = [];

        foreach ($operations as $operation) {
            $taskName = $operation->getTaskName();

            if (!isset($grouped[$taskName])) {
                $grouped[$taskName] = new Migration(
                    migrationName: $taskName,
                    migrationDescription: $operation->getDescription()
                );
            }

            $grouped[$taskName]->addMigrationOperation($operation);
        }

        return array_values($grouped);
    }
}
