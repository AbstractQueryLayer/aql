<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface StorageCollectionMutableInterface extends StorageCollectionInterface
{
    public function registerStorage(string $storageName, string $storageClass): void;

    public function addStorage(string $storageName, StorageInterface $storage): void;
}
