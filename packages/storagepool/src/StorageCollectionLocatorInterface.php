<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Storage\StorageCollectionInterface;

interface StorageCollectionLocatorInterface
{
    public function getStorageCollection(): StorageCollectionInterface;
}
