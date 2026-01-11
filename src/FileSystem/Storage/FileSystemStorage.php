<?php

declare(strict_types=1);

namespace IfCastle\AQL\FileSystem\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageInterface;
use League\Flysystem\FilesystemOperator;

class FileSystemStorage implements StorageInterface, QueryExecutorResolverInterface
{
    protected FilesystemOperator $filesystemOperator;

    protected string|null $storageName = null;

    protected StorageException|null $lastError = null;

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    #[\Override]
    public function setStorageName(string $storageName): static
    {
        $this->storageName = $storageName;

        return $this;
    }

    #[\Override]
    public function connect(): void
    {
        // TODO: Implement connect() method.
    }

    #[\Override]
    public function getLastError(): ?StorageException
    {
        return $this->lastError;
    }

    #[\Override]
    public function disconnect(): void
    {
        // TODO: Implement disconnect() method.
    }

    #[\Override]
    public function resolveQueryExecutor(
        BasicQueryInterface $basicQuery,
        ?EntityInterface     $entity = null
    ): ?QueryExecutorInterface {
        // TODO: Implement resolveQueryExecutor() method.
    }
}
