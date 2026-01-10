<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\InsertUpdateResult;
use IfCastle\AQL\Executor\QueryExecutorDummy;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\Result\ResultFetched;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\Exceptions\LogicalException;

class SomeStorageMock implements StorageInterface, AqlStorageInterface, QueryExecutorResolverInterface
{
    public const string NAME        = 'some_storage_name';

    /**
     * @var string[]
     */
    public array $executedSql       = [];

    /**
     * @var ResultInterface[]
     */
    public array $queryResults      = [];

    public ?StorageException $lastError = null;

    private string $storageName     = self::NAME;

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

    public function reset(): void
    {
        $this->executedSql          = [];
        $this->queryResults         = [];
        $this->lastError            = null;
    }

    /**
     * @throws LogicalException
     * @throws StorageException
     */
    #[\Override]
    public function executeAql(BasicQueryInterface  $query,
        ?object $context = null
    ): ResultInterface {
        $aql                        = $query->getAql(true);
        $this->executedSql[]        = $aql;

        if ($this->lastError !== null) {
            throw $this->lastError;
        }

        if (\array_key_exists($aql, $this->queryResults)) {
            if (\is_array($this->queryResults[$aql])) {
                return new ResultFetched($this->queryResults[$aql]);
            }

            if (\is_scalar($this->queryResults[$aql])) {
                $result             = new ResultFetched([]);
                $result->setInsertUpdateResult(new InsertUpdateResult(lastInsertedId: $this->queryResults[$aql]));
            } elseif ($this->queryResults[$aql] instanceof \DateTimeImmutable) {
                $result             = new ResultFetched([]);
                $result->setInsertUpdateResult(new InsertUpdateResult(lastDateTime: $this->queryResults[$aql]));
            } elseif ($this->queryResults[$aql] instanceof StorageException) {
                throw $this->queryResults[$aql];
            } else {
                throw new \TypeError('Invalid result type: ' . \get_debug_type($this->queryResults[$aql]));
            }

            return $this->queryResults[$aql];
        }

        return new ResultFetched([]);
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        return new QueryExecutorDummy();
    }
}
