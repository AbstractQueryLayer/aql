<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\DesignPatterns\Pool\DecoratorInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\CompositeException;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * The storages one execution context holds. The first findStorage() of a name borrows a storage from its pool, later
 * ones return the same storage, so a transaction and the results it reads stay on one connection. The storages go
 * back to their pools on release(), or when the context drops the proxy.
 */
final class StorageCollectionPoolProxy implements StorageCollectionInterface, DisposableInterface
{
    /**
     * @var array<string, StorageInterface>
     */
    private array $storages         = [];

    /**
     * The decorator a pool lent for each storage in $storages.
     *
     * @var array<string, DecoratorInterface<object>>
     */
    private array $decorators       = [];

    /**
     * @var \WeakReference<StorageCollectionPoolInterface>
     */
    private readonly \WeakReference $storageCollectionPool;

    public function __construct(StorageCollectionPoolInterface $storageCollectionPool)
    {
        $this->storageCollectionPool = \WeakReference::create($storageCollectionPool);
    }

    /**
     * @throws StorageException when the pool of the storage has no free storage
     * @throws UnexpectedValueType when the pool holds something other than a storage
     */
    #[\Override]
    public function findStorage(?string $storageName = null): ?StorageInterface
    {
        $storageName                ??= StorageCollectionInterface::STORAGE_MAIN;

        if (\array_key_exists($storageName, $this->storages)) {
            return $this->storages[$storageName];
        }

        $storage                    = $this->storageCollectionPool->get()?->borrowStorage($storageName);

        if (false === $storage instanceof DecoratorInterface) {
            return $storage;
        }

        $original                   = $storage->getOriginalObject();

        if (false === $original instanceof StorageInterface) {
            if ($storage instanceof DisposableInterface) {
                $storage->dispose();
            }

            throw new UnexpectedValueType('storage.' . $storageName, $original, StorageInterface::class);
        }

        $this->decorators[$storageName] = $storage;
        $this->storages[$storageName] = $original;

        return $original;
    }

    /**
     * Gives every borrowed storage back to its pool. A transaction still open on a storage is rolled back first and
     * reported; a storage whose transaction stays open after the rollback is dropped from its pool instead of being
     * given back, when its pool lent a StorageDecorator.
     *
     * @throws \Throwable|CompositeException after all storages are released, when a transaction was open or a
     *                                       rollback failed
     */
    public function release(): void
    {
        $errors                     = $this->releaseAll();

        if (\count($errors) === 1) {
            throw $errors[0];
        }

        if ($errors !== []) {
            throw new CompositeException('Errors while releasing pooled storages', ...$errors);
        }
    }

    #[\Override]
    public function dispose(): void
    {
        $this->release();
    }

    public function __destruct()
    {
        // A destructor must not throw: the storages are released, the errors are reported as a warning.
        $messages                   = \array_map(
            static fn(\Throwable $error): string => $error->getMessage(),
            $this->releaseAll()
        );

        if ($messages !== []) {
            \trigger_error('Pooled storages released with errors: ' . \implode('; ', $messages), E_USER_WARNING);
        }
    }

    /**
     * @return list<\Throwable>
     */
    private function releaseAll(): array
    {
        $decorators                 = $this->decorators;
        $storages                   = $this->storages;
        $this->decorators           = [];
        $this->storages             = [];
        $errors                     = [];

        foreach ($decorators as $storageName => $decorator) {
            try {
                $this->releaseOne($storages[$storageName], $decorator, $errors);
            } catch (\Throwable $exception) {
                $errors[]           = $exception;
            }
        }

        return $errors;
    }

    /**
     * @param DecoratorInterface<object> $decorator
     * @param list<\Throwable>           $errors
     */
    private function releaseOne(StorageInterface $storage, DecoratorInterface $decorator, array &$errors): void
    {
        try {
            if ($this->rollBackOpenTransactions($storage)) {
                $errors[]           = $this->openTransactionError($storage);
            }
        } catch (\Throwable $exception) {
            $errors[]               = $exception;
        }

        // Decided by the state, not by the error: a rollback of a transaction spanning several storages fails for
        // all of them when one fails, while only that one keeps the transaction open.
        if ($decorator instanceof StorageDecorator
            && $storage instanceof TransactionAwareInterface && $storage->getTransaction() !== null) {
            $decorator->discard();
        } elseif ($decorator instanceof DisposableInterface) {
            $decorator->dispose();
        }
    }

    /**
     * Rolls back what the context left open on the storage: the outermost transaction, which ends every savepoint
     * inside it too.
     *
     * @return bool whether a transaction was open
     * @throws \Throwable when the rollback fails or leaves the transaction open
     */
    private function rollBackOpenTransactions(StorageInterface $storage): bool
    {
        if (false === $storage instanceof TransactionAwareInterface) {
            return false;
        }

        $wasOpen                    = false;

        while (null !== $transaction = $storage->getTransaction()) {
            $outermost              = $this->outermost($transaction);

            if ($wasOpen && $outermost === $rolledBack) {
                throw $this->openTransactionError($storage, 'stays open after its rollback');
            }

            $outermost->rollBack();
            $rolledBack             = $outermost;
            $wasOpen                = true;
        }

        return $wasOpen;
    }

    private function openTransactionError(
        StorageInterface $storage,
        string $state               = 'was open when the storage was released, and was rolled back'
    ): StorageException {
        return new StorageException([
            'template'              => 'The transaction of the storage {storageName} ({storage}) {state}',
            'storage'               => $storage::class,
            'storageName'           => $storage->getStorageName(),
            'state'                 => $state,
        ]);
    }

    private function outermost(TransactionInterface $transaction): TransactionInterface
    {
        while (null !== $parent = $transaction->getParentTransaction()) {
            $transaction            = $parent;
        }

        return $transaction;
    }
}
