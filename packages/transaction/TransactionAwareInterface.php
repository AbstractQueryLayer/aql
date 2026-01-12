<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

interface TransactionAwareInterface
{
    public function getTransaction(): ?TransactionInterface;
}
