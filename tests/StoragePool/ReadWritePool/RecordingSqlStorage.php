<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool\ReadWritePool;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\ResultNull;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SqlStorageInterface;

/**
 * Storage that records every executeSql call together with the context it received.
 */
class RecordingSqlStorage implements SqlStorageInterface
{
    /**
     * @var array<int, array{sql: string, context: ?object}>
     */
    public array $calls             = [];

    protected ?string $storageName  = null;

    public function __construct(public readonly string $name) {}

    #[\Override]
    public function executeSql(string $sql, ?object $context = null): ResultInterface
    {
        $this->calls[]              = ['sql' => $sql, 'context' => $context];

        return new ResultNull();
    }

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function disconnect(): void {}

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
    public function quote(mixed $value): string
    {
        return "'" . $value . "'";
    }

    #[\Override]
    public function escape(string $value): string
    {
        return $value;
    }

    #[\Override]
    public function lastInsertId(): string|int|float|null
    {
        return null;
    }

    #[\Override]
    public function getLastError(): ?StorageException
    {
        return null;
    }
}
