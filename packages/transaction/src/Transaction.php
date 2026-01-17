<?php

declare(strict_types=1);

namespace IfCastle\AQL\Transaction;

use IfCastle\Exceptions\CompositeException;

class Transaction implements TransactionInterface
{
    /**
     * @var callable|null
     */
    protected $finalizeHandler;

    protected ?string $transactionId = null;

    /**
     * @var array<string, callable>
     */
    protected array $storageHandlers = [];

    protected ?TransactionInterface $parent = null;

    protected TransactionStatusEnum $status = TransactionStatusEnum::UNDEFINED;

    public function __construct(?callable $finalizeHandler = null, protected ?IsolationLevelEnum $isolationLevel = null)
    {
        $this->finalizeHandler      = $finalizeHandler;
    }

    #[\Override]
    public function getStatus(): TransactionStatusEnum
    {
        return $this->status;
    }

    #[\Override]
    public function getIsolationLevel(): IsolationLevelEnum|null
    {
        return $this->isolationLevel;
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function commit(): void
    {
        $this->handler(TransactionFinishedStatusEnum::COMMITTED);
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function rollBack(?\Throwable $throwable = null): void
    {
        $this->handler(TransactionFinishedStatusEnum::ROLLED_BACK, $throwable);
    }

    #[\Override]
    public function openTransaction(string $storage, callable $finalizeHandler): void
    {
        if ($this->status === TransactionStatusEnum::COMMITTED || $this->status === TransactionStatusEnum::ROLLED_BACK) {
            throw new \LogicException('Transaction already finished');
        }

        $this->status               = TransactionStatusEnum::OPENED;

        if (\array_key_exists($storage, $this->storageHandlers)) {
            throw new \LogicException('Transaction already started for storage: ' . $storage);
        }

        $this->storageHandlers[$storage] = $finalizeHandler;
    }

    #[\Override]
    public function isTransactionOpened(?string $storage = null): bool
    {
        if ($storage === null) {
            return $this->storageHandlers !== [];
        }

        return \array_key_exists($storage, $this->storageHandlers);
    }

    #[\Override]
    public function getTransactionId(): ?string
    {
        if ($this->transactionId === null && $this->parent !== null) {
            return $this->parent->getTransactionId();
        }

        return $this->transactionId;
    }

    #[\Override]
    public function setTransactionId(string $transactionId): static
    {
        if ($this->transactionId !== null) {
            throw new \LogicException('Transaction id already defined');
        }

        $this->transactionId        = $transactionId;

        return $this;
    }

    #[\Override]
    public function getParentTransaction(): ?TransactionInterface
    {
        return $this->parent;
    }

    #[\Override]
    public function setParentTransaction(TransactionInterface $transaction): static
    {
        $this->parent               = $transaction;

        return $this;
    }

    /**
     * @throws \Throwable
     */
    protected function handler(TransactionFinishedStatusEnum $status, ?\Throwable $throwable = null): void
    {
        $this->status               = $status->toTransactionStatus();

        $finalizeHandlers           = \array_reverse($this->storageHandlers);

        if ($this->finalizeHandler !== null) {
            $finalizeHandlers[]     = $this->finalizeHandler;
        }

        $this->storageHandlers      = [];
        $this->finalizeHandler      = null;

        $errors                     = [];

        if ($throwable !== null) {
            $errors[]               = $throwable;
        }

        foreach ($finalizeHandlers as $storageHandler) {

            if ($errors !== []) {
                $status             = false;
            }

            try {
                $storageHandler($status, $this);
            } catch (\Throwable $exception) {
                $errors[]           = $exception;
            }
        }

        $this->parent               = null;

        if (\count($errors) === 1) {
            throw $errors[0];
        }

        if (\count($errors) > 1) {
            throw new CompositeException('Errors while transaction finalize', ...$errors);
        }
    }
}
