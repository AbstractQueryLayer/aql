<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

final class PhpMigrationExecutor implements MigrationOperationExecutorInterface
{
    #[\Override]
    public function execute(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'php') {
            throw new \InvalidArgumentException('PhpMigrationExecutor can only execute PHP migrations');
        }

        $code = $operation->getCode();
        $this->executePhpCode($code, 'up');
    }

    #[\Override]
    public function rollback(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'php') {
            throw new \InvalidArgumentException('PhpMigrationExecutor can only rollback PHP migrations');
        }

        $code = $operation->getRollbackCode();

        if (empty($code)) {
            $code = $operation->getCode();
        }

        $this->executePhpCode($code, 'down');
    }

    #[\Override]
    public function supports(MigrationOperationInterface $operation): bool
    {
        return $operation->getType() === 'php';
    }

    private function executePhpCode(string $code, string $method): void
    {
        // Execute PHP code and call the specified method (up/down)
        $tempFile = tempnam(sys_get_temp_dir(), 'migration_');

        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file for PHP migration');
        }

        try {
            file_put_contents($tempFile, $code);
            $instance = require $tempFile;

            if (!is_object($instance)) {
                throw new \RuntimeException('PHP migration must return an object instance');
            }

            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("PHP migration must have {$method}() method");
            }

            $instance->$method();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
