<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionStatusEnum;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DI\DisposableInterface;

final class StorageCollectionPoolProxy implements StorageCollectionInterface, StorageReturnProxyInterface, DisposableInterface
{
    /**
     * @var \WeakReference[]
     */
    private array $borrowedStorages = [];

    /**
     * @var StorageInterface[]
     */
    private array $borrowedOriginalStorages = [];

    private readonly \WeakReference $storageCollectionPool;

    public function __construct(StorageCollectionPoolInterface $storageCollectionPool)
    {
        $this->storageCollectionPool = \WeakReference::create($storageCollectionPool);
    }

    #[\Override]
    public function findStorage(?string $storageName = null): ?StorageInterface
    {
        if ($storageName === null) {
            $storageName            = StorageCollectionInterface::STORAGE_MAIN;
        }

        if (\array_key_exists($storageName, $this->borrowedStorages)) {
            $storage                = $this->borrowedStorages[$storageName]->get();

            if ($storage === null) {
                unset($this->borrowedStorages[$storageName]);
            }
        } elseif (\array_key_exists($storageName, $this->borrowedOriginalStorages)) {
            $storage                = $this->storageCollectionPool->get()?->createStorageDecorator($this->borrowedOriginalStorages[$storageName]);

            if ($storage instanceof StorageReturnProxyAbleInterface) {
                $storage->setReturnProxy($this);
            }
        } else {
            $storage                = $this->storageCollectionPool?->get()->findStorage($storageName);

            if ($storage instanceof StorageReturnProxyAbleInterface) {
                $storage->setReturnProxy($this);
            }
        }

        // Save only decorated storages
        if ($storage instanceof DecoratorInterface && false === \array_key_exists($storageName, $this->borrowedStorages)) {
            $this->borrowedStorages[$storageName] = \WeakReference::create($storage);
        }

        return $storage;
    }

    #[\Override]
    public function returnStorage(object $storage): bool
    {
        if (false === $storage instanceof StorageInterface) {
            return true;
        }

        $storageName                = $storage->getStorageName();

        if (false === \array_key_exists((string) $storageName, $this->borrowedStorages)
        && false === \array_key_exists((string) $storageName, $this->borrowedOriginalStorages)) {
            return true;
        }

        if (\array_key_exists($storageName, $this->borrowedStorages)) {
            unset($this->borrowedStorages[$storageName]);
        }

        if ($this->shouldStorageReturn($storage)) {

            if (\array_key_exists($storageName, $this->borrowedOriginalStorages)) {
                $this->storageCollectionPool->get()?->returnStorage($this->borrowedOriginalStorages[$storageName]);
                unset($this->borrowedOriginalStorages[$storageName]);
            }

            return true;
        }

        $this->borrowedOriginalStorages[$storageName] = $storage;

        return false;
    }

    protected function shouldStorageReturn(StorageInterface $storage): bool
    {
        if (false === $storage instanceof TransactionAwareInterface) {
            return true;
        }

        $currentTransaction         = $storage->getTransaction();

        if ($currentTransaction === null) {
            return true;
        }

        return \in_array($currentTransaction->getStatus(), [TransactionStatusEnum::COMMITTED, TransactionStatusEnum::ROLLED_BACK, true]);
    }

    public function __destruct()
    {
        $this->dispose();
    }

    #[\Override]
    public function dispose(): void
    {
        $storageCollectionPool      = $this->storageCollectionPool->get();
        $borrowedStorages           = $this->borrowedOriginalStorages;
        $this->borrowedStorages         = [];

        $this->borrowedOriginalStorages = [];

        if ($storageCollectionPool === null) {
            return;
        }

        foreach ($borrowedStorages as $storage) {
            $this->emergencyReleaseStorageIfNeed($storage);
            $storageCollectionPool->returnStorage($storage);
        }
    }

    protected function emergencyReleaseStorageIfNeed(StorageInterface $storage): void
    {
        if (false === $storage instanceof TransactionAwareInterface) {
            return;
        }

        $currentTransaction         = $storage->getTransaction();

        if ($currentTransaction === null) {
            return;
        }

        if (\in_array($currentTransaction->getStatus(), [TransactionStatusEnum::COMMITTED, TransactionStatusEnum::ROLLED_BACK, true])) {
            return;
        }

        $currentTransaction->rollBack(new StorageException([
            'template'              => 'The storage {storageName} ({storage}) was released while the transaction '
                                       . 'was not completed! Critical state integrity error.',
            'storage'               => $storage::class,
            'storageName'           => $storage->getStorageName(),
        ]));
    }
}
