<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

enum TransactionStatusEnum
{
    case UNDEFINED;
    case OPENED;
    case COMMITTED;
    case ROLLED_BACK;
}
