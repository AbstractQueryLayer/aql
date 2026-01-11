<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\Exceptions\InvalidExecutorType;
use IfCastle\AQL\MigrationTool\Exceptions\InvalidMigrationFile;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

final class PhpMigrationExecutor implements MigrationOperationExecutorInterface
{
    #[\Override]
    public function execute(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'php') {
            throw new InvalidExecutorType(self::class, 'php', $operation->getType());
        }

        $code = $operation->getCode();
        $this->executePhpCode($code, 'up', $operation->getFilePath());
    }

    #[\Override]
    public function rollback(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'php') {
            throw new InvalidExecutorType(self::class, 'php', $operation->getType());
        }

        $code = $operation->getRollbackCode();

        if (empty($code)) {
            $code = $operation->getCode();
        }

        $this->executePhpCode($code, 'down', $operation->getFilePath());
    }

    #[\Override]
    public function supports(MigrationOperationInterface $operation): bool
    {
        return $operation->getType() === 'php';
    }

    private function executePhpCode(string $code, string $method, string $filePath): void
    {
        // Execute PHP code and call the specified method (up/down)
        $tempFile = tempnam(sys_get_temp_dir(), 'migration_');

        if ($tempFile === false) {
            throw new InvalidMigrationFile($filePath, 'Failed to create temporary file for execution');
        }

        try {
            file_put_contents($tempFile, $code);
            $instance = require $tempFile;

            if (!is_object($instance)) {
                throw new InvalidMigrationFile($filePath, 'PHP migration must return an object instance');
            }

            if (!method_exists($instance, $method)) {
                throw new InvalidMigrationFile($filePath, "PHP migration must have {$method}() method");
            }

            $instance->$method();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
