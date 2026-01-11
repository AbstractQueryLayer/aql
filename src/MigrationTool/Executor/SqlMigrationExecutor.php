<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Executor;

use IfCastle\AQL\MigrationTool\Exceptions\InvalidExecutorType;
use IfCastle\AQL\MigrationTool\Exceptions\RollbackCodeMissing;
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
            throw new InvalidExecutorType(self::class, 'sql', $operation->getType());
        }

        $this->storage->execute($operation->getCode());
    }

    #[\Override]
    public function rollback(MigrationOperationInterface $operation): void
    {
        if ($operation->getType() !== 'sql') {
            throw new InvalidExecutorType(self::class, 'sql', $operation->getType());
        }

        $rollbackCode = $operation->getRollbackCode();

        if (empty($rollbackCode)) {
            throw new RollbackCodeMissing($operation->getTaskName(), $operation->getVersion());
        }

        $this->storage->execute($rollbackCode);
    }

    #[\Override]
    public function supports(MigrationOperationInterface $operation): bool
    {
        return $operation->getType() === 'sql';
    }
}
