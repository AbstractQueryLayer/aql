<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\StoragePool\ReadWritePool\RecordingSqlStorage;

/**
 * A storage built from its class name alone, as StorageCollection::instanciateStorage does.
 */
final class NamelessStorage extends RecordingSqlStorage
{
    public function __construct()
    {
        parent::__construct('nameless');
    }
}
