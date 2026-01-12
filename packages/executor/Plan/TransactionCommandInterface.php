<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Transaction\TransactionAwareInterface;

interface TransactionCommandInterface extends CommandInterface, TransactionAwareInterface {}
