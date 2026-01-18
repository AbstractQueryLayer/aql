<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface StorageCollectionInterface
{
    public const string STORAGE_MAIN = 'main';

    public function findStorage(?string $storageName = null): ?StorageInterface;
}
