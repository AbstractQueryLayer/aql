<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;

class SqlQueryExecutorTestCase extends TestCaseWithDiContainer
{
    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);

        $containerBuilder->bindObject(StorageCollectionInterface::class, new StorageCollection([
            StorageCollectionInterface::STORAGE_MAIN    => MySql::class,
        ]));
    }
}
