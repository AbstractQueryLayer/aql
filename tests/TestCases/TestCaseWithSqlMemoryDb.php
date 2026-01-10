<?php

declare(strict_types=1);

namespace IfCastle\AQL\TestCases;

use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageCollectionMutableInterface;

abstract class TestCaseWithSqlMemoryDb extends TestCaseWithDiContainer
{
    #[\Override]
    protected static function defineMainStorage(StorageCollectionMutableInterface $storageCollection): void
    {
        $storageCollection->registerStorage(StorageCollectionInterface::STORAGE_MAIN, MemorySQLiteStorage::class);
    }
}
