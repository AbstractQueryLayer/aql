<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

interface StorageCollectionPoolInterface
{
    /**
     * Borrows the storage by name: a storage without a pool as is, a pooled one as the decorator its pool lent, which
     * gives the storage back when disposed. Null when no storage has the name.
     */
    public function borrowStorage(string $storageName): object|null;
}
