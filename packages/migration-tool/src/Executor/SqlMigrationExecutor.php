<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\MigrationOperationInterface;
use IfCastle\AQL\Storage\StorageInterface;

final readonly class SqlMigrationExecutor implements MigrationOperationExecutorInterface
{
    public function __construct(
        private StorageInterface $storage
    ) {}

    #[\Override]
    public function execute(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'sql') {
            throw new MigrationException("SqlMigrationExecutor can only execute SQL migrations, got: {$operation->getType()}");
        }

        $this->storage->executeSql($operation->getCode());
    }

    #[\Override]
    public function rollback(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'sql') {
            throw new MigrationException("SqlMigrationExecutor can only execute SQL migrations, got: {$operation->getType()}");
        }

        $rollbackCode = $operation->getRollbackCode();

        if (empty($rollbackCode)) {
            throw new MigrationException("Rollback code is missing for migration: {$operation->getTaskName()} v{$operation->getVersion()}");
        }

        $this->storage->executeSql($rollbackCode);
    }

    #[\Override]
    public function supports(MigrationOperationInterface $operation): bool
    {
        return $operation->getType() === 'sql';
    }
}
