<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\DesignPatterns\Pool\PoolInterface;
use IfCastle\DesignPatterns\Pool\ReturnFactoryInterface;

final class StorageReturnFactory implements ReturnFactoryInterface
{
    #[\Override]
    public function createDecorator(object $originalObject, PoolInterface $pool): object
    {
        return new StorageDecorator($originalObject, $pool);
    }
}
