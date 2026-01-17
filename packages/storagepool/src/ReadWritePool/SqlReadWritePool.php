<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool\ReadWritePool;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\ReaderWriterInterface;
use IfCastle\AQL\Storage\SqlStorageInterface;
use IfCastle\AQL\Transaction\TransactionAbleInterface;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;

/**
 * ## SqlReadWritePool.
 *
 * Supports pooling logic for databases.
 * Where the first database is read-only and the second is read-write
 */
class SqlReadWritePool implements SqlStorageInterface, TransactionAbleInterface, QueryExecutorResolverInterface
{
    protected ?string $storageName  = null;

    protected mixed $readerProvider;

    protected mixed $writerProvider;

    protected SqlStorageInterface|null $reader = null;

    protected SqlStorageInterface|null $writer = null;

    protected SqlStorageInterface|null $lastUsed = null;

    public function __construct(
        callable $readerProvider,
        callable $writerProvider,
    ) {
        $this->readerProvider       = $readerProvider;
        $this->writerProvider       = $writerProvider;
    }

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    #[\Override]
    public function setStorageName(string $storageName): static
    {
        $this->storageName          = $storageName;

        return $this;
    }

    /**
     *
     *
     * @throws  StorageException
     */
    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        $this->instantiate(true);
        return $this->reader->resolveQueryExecutor($basicQuery, $entity);
    }

    /**
     * @throws StorageException
     */
    #[\Override]
    public function executeSql(string $sql, ?object $context = null): ResultInterface
    {
        static $selectLength        = null;
        static $withLength          = null;

        if ($selectLength === null) {
            $selectLength       = \strlen('SELECT');
        }

        if ($withLength === null) {
            $withLength         = \strlen('WITH');
        }

        //
        // Writer used if
        // 1. The context indicates that only a WRITER should be used.
        // 2. $sql is not equal to select
        // 3. Writer is under Transaction
        //
        if (($context instanceof ReaderWriterInterface && $context->useOnlyWriter())
            || (
                \strtoupper(\substr($sql, 0, $selectLength)) !== 'SELECT'
                && \strtoupper(\substr($sql, 0, $withLength)) !== 'WITH'
            )
           || ($this->writer === null || ($this->writer instanceof TransactionAwareInterface && $this->writer->getTransaction() !== null))) {

            if ($this->writer === null) {
                $this->instantiate(false);
            }

            $this->lastUsed     = $this->writer;
            return $this->writer->executeSql($sql);
        }

        if ($this->reader === null) {
            $this->instantiate(true);
        }

        $this->lastUsed         = $this->reader;

        return $this->reader->executeSql($sql);
    }

    /**
     * @throws StorageException
     */
    #[\Override]
    public function quote(mixed $value): string
    {
        if ($this->lastUsed !== null) {
            return $this->lastUsed->quote($value);
        }

        if ($this->reader !== null) {
            return $this->reader->quote($value);
        }

        if ($this->writer !== null) {
            return $this->writer->quote($value);
        }

        $this->instantiate(true);
        return $this->reader->quote($value);

    }

    /**
     * @throws StorageException
     */
    #[\Override]
    public function escape(string $value): string
    {
        if ($this->lastUsed !== null) {
            return $this->lastUsed->escape($value);
        }

        if ($this->reader !== null) {
            return $this->reader->escape($value);
        }

        if ($this->writer !== null) {
            return $this->writer->escape($value);
        }

        $this->instantiate(true);
        return $this->reader->escape($value);

    }

    #[\Override]
    public function lastInsertId(): string|int|float|null
    {
        if ($this->writer === null) {
            $this->instantiate(false);
        }

        return $this->writer->lastInsertId();
    }

    /**
     * @throws StorageException
     */
    #[\Override]
    public function beginTransaction(TransactionInterface $transaction): void
    {
        if ($this->writer === null) {
            $this->instantiate(false);
        }

        if (false === $this->writer instanceof TransactionAbleInterface) {
            throw new StorageException([
                'template'          => 'Storage {storage} does not support transactions (TransactionAbleInterface)',
                'storage'           => $this->writer::class,
            ]);
        }

        $this->writer->beginTransaction($transaction);
    }

    #[\Override]
    public function getTransaction(): ?TransactionInterface
    {
        if ($this->writer instanceof TransactionAwareInterface) {
            return $this->writer->getTransaction();
        }

        return null;
    }

    #[\Override]
    public function getLastError(): ?StorageException
    {
        if ($this->lastUsed !== null) {
            return $this->lastUsed->getLastError();
        }

        if ($this->reader !== null) {
            return $this->reader->getLastError();
        }

        return $this->writer?->getLastError();
    }

    #[\Override]
    public function disconnect(): void
    {
        if ($this->reader !== null) {
            $this->reader->disconnect();
            $this->reader           = null;
        }

        if ($this->writer !== null) {
            $this->writer->disconnect();
            $this->writer           = null;
        }
    }

    /**
     * @throws StorageException
     */
    protected function instantiate(bool $isReader): void
    {
        $provider                   = $isReader ? $this->readerProvider : $this->writerProvider;
        $driver                     = $provider();

        if ($isReader) {
            $this->reader           = $driver;
        } else {
            $this->writer           = $driver;
        }
    }
}
