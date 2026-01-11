<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\Storage\StorageInterface;

final class SqlMigrationExecutor implements MigrationOperationExecutorInterface
{
    public function __construct(
        private readonly StorageInterface $storage
    ) {}

    #[\Override]
    public function execute(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'sql') {
            throw new \InvalidArgumentException('SqlMigrationExecutor can only execute SQL migrations');
        }

        $this->storage->execute($operation->getCode());
    }

    #[\Override]
    public function rollback(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'sql') {
            throw new \InvalidArgumentException('SqlMigrationExecutor can only rollback SQL migrations');
        }

        $rollbackCode = $operation->getRollbackCode();

        if (empty($rollbackCode)) {
            throw new \RuntimeException('Rollback code is empty for migration: ' . $operation->getTaskName());
        }

        $this->storage->execute($rollbackCode);
    }

    #[\Override]
    public function supports(MigrationOperationInterface $operation): bool
    {
        return $operation->getType() === 'sql';
    }
}
