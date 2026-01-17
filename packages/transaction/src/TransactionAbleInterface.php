<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

interface TransactionAbleInterface extends TransactionAwareInterface
{
    public function beginTransaction(TransactionInterface $transaction): void;
}
