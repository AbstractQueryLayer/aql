<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql\Storage;

use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableInterface;
use IfCastle\AQL\PdoDriver\PDOAbstract;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\ServerHasGoneAwayException;
use IfCastle\AQL\Storage\Exceptions\StorageException;

class PostgreSql extends PDOAbstract implements FunctionHandlerInterface
{
    #[\Override]
    public function escape(string $value): string
    {
        return '"' . $value . '"';
    }

    #[\Override]
    protected function normalizeException(\PDOException $exception, string $sql): StorageException
    {
        return match ($exception->errorInfo[0]) {
            // PostgreSQL error codes
            '40001'             => new RecoverableException($exception->errorInfo[2], $sql, $exception), // Deadlock
            '08006'             => new ServerHasGoneAwayException($exception->errorInfo[2], $sql, $exception), // Connection Failure
            '23505'             => new DuplicateKeysException($exception->errorInfo[2], $sql, $exception), // Unique violation
            default             => new QueryException($exception->errorInfo[2], $sql, $exception)
        };
    }

    #[\Override]
    protected function isNestedTransactionsSupported(): bool
    {
        return true; // PostgreSQL поддерживает вложенные транзакции с использованием savepoints
    }

    #[\Override]
    public function newEntityToTableGenerator(EntityInterface $entity): EntityToTableInterface
    {
        return new EntityToTable($entity);
    }

    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        switch ($function->getFunctionName()) {
            case 'DATE_ADD':
            case 'DATE_SUB':
            case 'NOW':
            case 'COUNT':
            case 'SUM':
            case 'MIN':
            case 'MAX':
            case 'AVG':
            case 'CONCAT':
            case 'CONCAT_WS':
            case 'SUBSTRING':
                // 'DATE + interval'
                $function->resolveSelf();
                break;
        }
    }
}
