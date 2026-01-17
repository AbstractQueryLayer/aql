<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

enum IsolationLevelEnum
{
    case UNCOMMITTED;
    case COMMITTED;
    case REPEATABLE;
    case SERIALIZABLE;
}
