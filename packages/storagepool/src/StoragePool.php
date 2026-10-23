<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DesignPatterns\Pool\Pool;
use IfCastle\DesignPatterns\Pool\ReturnFactoryInterface;
use IfCastle\DesignPatterns\Pool\Stack;
use IfCastle\DesignPatterns\Pool\StackInterface;

/**
 * A pool of storage drivers. It keeps at least one idle driver by default, so a returned driver serves the next
 * borrow instead of a new connection.
 */
class StoragePool extends Pool implements StoragePoolInterface
{
    public function __construct(
        FactoryInterface $factory,
        int $maxPoolSize,
        int $minPoolSize            = 1,
        int $timeout                = -1,
        int $delayPoolReduction     = 0,
        StackInterface $stack       = new Stack(),
        ReturnFactoryInterface $returnFactory = new StorageReturnFactory()
    ) {
        parent::__construct(
            $factory,
            $maxPoolSize,
            $minPoolSize,
            $timeout,
            $delayPoolReduction,
            $stack,
            $returnFactory
        );
    }

}
