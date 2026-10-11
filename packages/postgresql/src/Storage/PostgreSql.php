<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql\Storage;

use IfCastle\AQL\Dsl\Sql\Constant\BinaryString;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\SqlQueryExecutor;
use IfCastle\AQL\Generator\Ddl\EntityToTableInterface;
use IfCastle\AQL\PdoDriver\PDOAbstract;
use IfCastle\AQL\PostgreSql\Executor\PostgreSqlQueryExecutor;
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

    /**
     * X'…' is a bit string in PostgreSQL, so bytea takes its hex input format instead. The backslash is literal
     * under standard_conforming_strings, on by default since PostgreSQL 9.1.
     *
     * @see https://www.postgresql.org/docs/current/datatype-binary.html
     */
    #[\Override]
    protected function quoteBinary(BinaryString $value): string
    {
        return '\'\\x' . \bin2hex($value->bytes) . '\'::bytea';
    }

    #[\Override]
    protected function normalizeException(\Throwable $exception, string $sql): StorageException
    {
        if (false === $exception instanceof \PDOException) {
            return new QueryException($exception->getMessage(), $sql, $exception);
        }

        $message                    = $exception->errorInfo[2] ?? $exception->getMessage();

        // PostgreSQL reports its own SQLSTATE: https://www.postgresql.org/docs/current/errcodes-appendix.html
        return match ($exception->errorInfo[0] ?? null) {
            '40001',                // serialization_failure
            '40P01'                 // deadlock_detected
                                    => new RecoverableException($message, $sql, $exception),
            '08006'                 => new ServerHasGoneAwayException($message, $sql, $exception), // connection_failure
            '23505'                 => new DuplicateKeysException($message, $sql, $exception), // unique_violation
            default                 => new QueryException($message, $sql, $exception)
        };
    }

    #[\Override]
    protected function newSqlQueryExecutor(): SqlQueryExecutor
    {
        return new PostgreSqlQueryExecutor();
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
                // 'DATE + interval'
                $function->resolveSelf();
                break;
            case 'DATE_SUB':
                // 'DATE - interval'
                $function->resolveSelf();
                break;
            case 'NOW':
            case 'COUNT':
            case 'SUM':
            case 'MIN':
            case 'MAX':
            case 'AVG':
            case 'CONCAT':
            case 'CONCAT_WS':
            case 'SUBSTRING':
                $function->resolveSelf();
                break;
        }
    }
}
