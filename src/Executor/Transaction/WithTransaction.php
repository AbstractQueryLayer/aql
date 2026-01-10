<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Transaction;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\AdditionalHandlerAwareInterface;
use IfCastle\AQL\Executor\AdditionalOptions;
use IfCastle\AQL\Executor\AdditionalOptionsInterface;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\OptionsWithAdditionalHandler;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQueryInterface;
use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionInterface;

/**
 * Strategy for executing queries under a transaction.
 */
class WithTransaction implements AqlExecutorInterface
{
    public static function addTransactionToContext(
        TransactionInterface $transaction,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): ExecutionContextInterface|AdditionalOptionsInterface {
        if ($executionContext instanceof ExecutionContextInterface) {
            $executionContext->withTransaction($transaction);
            return $executionContext;
        }

        if ($executionContext instanceof AdditionalHandlerAwareInterface) {
            return new OptionsWithAdditionalHandler(
                [], $executionContext->getAdditionalHandler(), $transaction
            );
        }

        if ($executionContext instanceof AdditionalOptionsInterface) {
            $executionContext->withTransaction($transaction);
            return $executionContext;
        }

        return new AdditionalOptions([], $transaction);
    }


    protected \WeakReference|null $transaction = null;

    public function __construct(public readonly AqlExecutorInterface $aqlExecutor) {}

    #[\Override]
    public function executeAql(BasicQueryInterface|PreprocessedQueryInterface            $query,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): ResultInterface|TupleInterface|InsertUpdateResultInterface {
        return $this->aqlExecutor->executeAql($query, self::addTransactionToContext($this->transaction?->get(), $executionContext));
    }

    #[\Override]
    public function preprocessingQuery(
        BasicQueryInterface $query,
        ?ExecutionContextInterface $executionContext = null
    ): void {
        $this->aqlExecutor->preprocessingQuery($query, $executionContext);
    }

    public function run(callable $function): mixed
    {
        $transaction                = new Transaction();
        $this->transaction          = \WeakReference::create($transaction);

        try {
            $result                 = $function($this);
            $transaction->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $transaction->rollBack();
            throw $throwable;
        } finally {
            $this->transaction      = null;
        }
    }
}
