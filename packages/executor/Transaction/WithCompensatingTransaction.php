<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Transaction;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\AdditionalHandlerAwareInterface;
use IfCastle\AQL\Executor\AdditionalOptionsInterface;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQueryInterface;
use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\AQL\Transaction\Transaction;

/**
 * Strategy for executing queries under a transaction.
 */
class WithCompensatingTransaction implements AqlExecutorInterface
{
    protected \WeakReference|null $transaction;

    /**
     * @var BasicQueryInterface[]
     */
    protected array $executedQueries = [];

    public function __construct(public readonly AqlExecutorInterface $aqlExecutor) {}

    #[\Override]
    public function executeAql(BasicQueryInterface|PreprocessedQueryInterface            $query,
        ExecutionContextInterface|AdditionalHandlerAwareInterface|AdditionalOptionsInterface|null $executionContext = null
    ): ResultInterface|TupleInterface|InsertUpdateResultInterface {
        $result                     = $this->aqlExecutor->executeAql(
            $query, WithTransaction::addTransactionToContext($this->transaction->get(), $executionContext)
        );

        $this->executedQueries[]    = $query;

        return $result;
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
        $transaction                = new Transaction($this->transactionHandler(...));
        $this->transaction          = \WeakReference::create($transaction);
        $this->defineTransactionId();

        try {
            $result                 = $function($this);
            $transaction->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $transaction->rollBack();
            throw $throwable;
        } finally {
            $this->transaction       = null;
            $this->executedQueries  = [];
        }
    }

    protected function defineTransactionId(): void
    {
        /* @todo make transaction unique id */
        $this->transaction->setTransactionId('');
    }

    protected function transactionHandler(bool $isCommit): void {}
}
