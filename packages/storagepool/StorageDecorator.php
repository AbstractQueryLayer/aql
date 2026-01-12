<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DesignPatterns\Pool\PoolInterface;
use IfCastle\DI\DisposableInterface;

final class StorageDecorator implements DecoratorInterface, StorageReturnProxyAbleInterface, DisposableInterface
{
    private \WeakReference|null $pool;

    private \WeakReference|null $returnProxy = null;

    public function __construct(private object|null $originalObject, PoolInterface $pool)
    {
        $this->pool                 = \WeakReference::create($pool);
    }

    #[\Override]
    public function setReturnProxy(StorageReturnProxyInterface $storageReturnProxy): void
    {
        if ($this->returnProxy !== null) {
            throw new \LogicException('Return proxy already set');
        }

        $this->returnProxy          = \WeakReference::create($storageReturnProxy);
    }

    public function __destruct()
    {
        $this->dispose();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->originalObject->{$name}(...$arguments);
    }

    #[\Override]
    public function getOriginalObject(): object|null
    {
        return $this->originalObject;
    }

    #[\Override]
    public function dispose(): void
    {
        if ($this->originalObject === null) {
            return;
        }

        $pool                       = $this->pool?->get();
        $originalObject             = $this->originalObject;
        $returnProxy                = $this->returnProxy?->get();
        $this->pool                 = null;
        $this->originalObject       = null;
        $this->returnProxy          = null;

        if (false !== $returnProxy?->returnStorage($this->originalObject)) {
            $pool?->return($originalObject);
        }
    }
}
