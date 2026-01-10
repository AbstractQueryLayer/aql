<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\InsertUpdateResult;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\Result\ResultFetched;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;

class SqlStorageMock implements SqlStorageInterface, QueryExecutorResolverInterface
{
    public string|int|float|null $lastInsertId = null;

    /**
     * @var string[]
     */
    public array $executedSql       = [];

    /**
     * @var ResultInterface[]
     */
    public array $queryResults      = [];

    public ?StorageException $lastError = null;

    public ?QueryExecutorInterface $queryExecutor = null;

    protected ?string $storageName  = null;

    public function reset(): void
    {
        $this->executedSql          = [];
        $this->queryResults         = [];
        $this->lastError            = null;
    }

    public function getLastSql(): ?string
    {
        if ($this->executedSql === []) {
            return null;
        }

        return $this->executedSql[\count($this->executedSql) - 1];
    }

    /**
     * @throws StorageException
     */
    #[\Override]
    public function executeSql(string $sql, ?object $context = null): ResultInterface
    {
        $this->executedSql[]        = $sql;

        if ($this->lastError !== null) {
            throw $this->lastError;
        }

        $sql                        = \trim((string) \preg_replace(['/\s+/'], ' ', $sql));

        if (\array_key_exists($sql, $this->queryResults)) {
            if (\is_array($this->queryResults[$sql])) {
                return new ResultFetched($this->queryResults[$sql]);
            }
            if (\is_scalar($this->queryResults[$sql])) {
                $result             = new ResultFetched([]);
                $result->setInsertUpdateResult(new InsertUpdateResult(lastInsertedId: $this->queryResults[$sql]));
            } elseif ($this->queryResults[$sql] instanceof \DateTimeImmutable) {
                $result             = new ResultFetched([]);
                $result->setInsertUpdateResult(new InsertUpdateResult(lastDateTime: $this->queryResults[$sql]));
            } elseif ($this->queryResults[$sql] instanceof StorageException) {
                throw $this->queryResults[$sql];
            } else {
                throw new \Exception('Invalid result type');
            }
            return $this->queryResults[$sql];
        }

        return new ResultFetched([]);
    }

    #[\Override]
    public function quote(mixed $value): string
    {
        return '\'' . \addslashes((string) $value) . '\'';
    }

    #[\Override]
    public function escape(string $value): string
    {
        return \sprintf('`%s`', $value);
    }

    #[\Override]
    public function lastInsertId(): string|int|float|null
    {
        return $this->lastInsertId;
    }

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

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function getLastError(): ?StorageException
    {
        return $this->lastError;
    }

    #[\Override]
    public function disconnect(): void {}

    #[\Override] public function resolveQueryExecutor(
        BasicQueryInterface $basicQuery,
        ?EntityInterface     $entity = null
    ): ?QueryExecutorInterface {
        return $this->queryExecutor;
    }
}
