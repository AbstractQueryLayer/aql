<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

interface WithTransactionInterface extends TransactionAwareInterface
{
    /**
     * Execute queries plan inside transaction.
     *
     *
     * @return  $this
     */
    public function withTransaction(?TransactionInterface $transaction = null): static;
}
