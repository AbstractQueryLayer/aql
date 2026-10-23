<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\StoragePool\ReadWritePool\RecordingSqlStorage;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\DI\DisposableInterface;

/** A recording storage with the transaction a test sets on it, which records whether it was disposed. */
final class TransactionalRecordingStorage extends RecordingSqlStorage implements TransactionAwareInterface, DisposableInterface
{
    public ?TransactionInterface $transaction = null;

    public bool $disposed           = false;

    #[\Override]
    public function dispose(): void
    {
        $this->disposed             = true;
    }

    #[\Override]
    public function getTransaction(): ?TransactionInterface
    {
        return $this->transaction;
    }
}
