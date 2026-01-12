<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

interface TransactionInterface
{
    public function getStatus(): TransactionStatusEnum;

    public function getIsolationLevel(): IsolationLevelEnum|null;

    public function commit(): void;

    public function rollBack(?\Throwable $throwable = null): void;

    public function openTransaction(string $storage, callable $finalizeHandler): void;

    public function isTransactionOpened(?string $storage = null): bool;

    public function getTransactionId(): ?string;

    public function setTransactionId(string $transactionId): static;

    public function getParentTransaction(): ?TransactionInterface;

    public function setParentTransaction(TransactionInterface $transaction): static;
}
