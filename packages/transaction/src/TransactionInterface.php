<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

interface TransactionInterface
{
    public function getStatus(): TransactionStatusEnum;

    public function getIsolationLevel(): IsolationLevelEnum|null;

    public function commit(): void;

    public function rollBack(?\Throwable $throwable = null): void;

    /**
     * Registers a storage the transaction runs on. When the transaction finishes, it calls the handler as
     * $finalizeHandler(bool $commit, TransactionInterface $transaction): true to commit the storage, false
     * to roll it back. Storages finish in the reverse order of registration; after a handler fails, the
     * rest roll back.
     *
     * Before commit() or rollBack() change anything, the transaction calls every $finishCheck with no
     * arguments; one that throws refuses the finish, and the transaction stays open with its storages.
     *
     * @param string $storage the storage key, unique within the transaction
     *
     * @throws \LogicException when the transaction has finished or the storage is already registered
     */
    public function openTransaction(string $storage, callable $finalizeHandler, ?callable $finishCheck = null): void;

    public function isTransactionOpened(?string $storage = null): bool;

    public function getTransactionId(): ?string;

    public function setTransactionId(string $transactionId): static;

    public function getParentTransaction(): ?TransactionInterface;

    public function setParentTransaction(TransactionInterface $transaction): static;
}
