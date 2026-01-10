<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

enum TransactionFinishedStatusEnum
{
    public function toTransactionStatus(): TransactionStatusEnum
    {
        return match ($this) {
            self::COMMITTED         => TransactionStatusEnum::COMMITTED,
            self::ROLLED_BACK       => TransactionStatusEnum::ROLLED_BACK,
        };
    }

    case COMMITTED;
    case ROLLED_BACK;
}
