<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Transaction\TransactionInterface;

class TransactionCommand extends Command implements TransactionCommandInterface
{
    public function __construct(protected TransactionInterface $transaction)
    {
        parent::__construct(ExecutionPlanInterface::FINALLY, $this->handleTransaction(...));
    }

    #[\Override]
    public function getTransaction(): ?TransactionInterface
    {
        return $this->transaction;
    }

    protected function handleTransaction(ExecutionContextInterface $context): void
    {
        if ($context->getResult() !== null) {
            $this->transaction->commit();
        } else {
            $this->transaction->rollBack();
        }
    }
}
