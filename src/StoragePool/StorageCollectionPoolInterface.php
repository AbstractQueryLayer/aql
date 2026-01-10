<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

interface StorageCollectionPoolInterface
{
    public function borrowStorage(string $storageName): object|null;

    public function returnStorage(object $object): void;

    public function createStorageDecorator(object $originalObject): object;
}
