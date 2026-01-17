<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\StorageException;

interface SqlStorageInterface extends StorageInterface
{
    /**
     * @var string
     */
    public const string INITIAL_QUERIES     = 'initial_queries';

    /**
     *
     * @throws RecoverableException
     * @throws QueryException
     * @throws StorageException
     *
     */
    public function executeSql(string $sql, ?object $context = null): ResultInterface;

    public function quote(mixed $value): string;

    public function escape(string $value): string;

    public function lastInsertId(): string|int|float|null;
}
