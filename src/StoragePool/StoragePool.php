<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DesignPatterns\Pool\Pool;
use IfCastle\DesignPatterns\Pool\ReturnFactoryInterface;
use IfCastle\DesignPatterns\Pool\Stack;
use IfCastle\DesignPatterns\Pool\StackInterface;

class StoragePool extends Pool implements StoragePoolInterface
{
    public function __construct(
        FactoryInterface $factory,
        int $maxPoolSize,
        int $minPoolSize            = 0,
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
