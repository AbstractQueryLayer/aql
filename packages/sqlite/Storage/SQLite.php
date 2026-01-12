<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableInterface;
use IfCastle\AQL\PdoDriver\PDOAbstract;
use IfCastle\AQL\SQLite\Ddl\Generator\EntityToTable;
use IfCastle\AQL\SQLite\Executor\SQLiteQueryExecutor;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\ServerHasGoneAwayException;
use IfCastle\AQL\Storage\Exceptions\StorageException;

class SQLite extends PDOAbstract implements FunctionHandlerInterface
{
    public function __construct(array $config)
    {
        if (!empty($config['dsn'])) {
            $config['dsn'] = 'sqlite:' . $config['dsn'];
        }

        parent::__construct($config);
    }

    #[\Override]
    protected function normalizeException(\Throwable $exception, string $sql): StorageException
    {
        if (false === $exception instanceof \PDOException) {
            return new QueryException($exception->getMessage(), $sql, $exception);
        }

        return match ($exception->errorInfo[0]) {
            // please see: https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html
            1213                => new RecoverableException($exception->errorInfo[2], $sql, $exception),
            2006                => new ServerHasGoneAwayException($exception->errorInfo[2], $sql, $exception),
            1022                => new DuplicateKeysException($exception->errorInfo[2], $sql, $exception),
            default             => new QueryException($exception->errorInfo[2], $sql, $exception)
        };
    }

    #[\Override]
    protected function isNestedTransactionsSupported(): bool
    {
        return false;
    }

    #[\Override]
    public function newEntityToTableGenerator(EntityInterface $entity): EntityToTableInterface
    {
        return new EntityToTable($entity);
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        return match ($basicQuery->getQueryAction()) {
            QueryInterface::ACTION_COPY,
            QueryInterface::ACTION_SELECT,
            QueryInterface::ACTION_COUNT,
            QueryInterface::ACTION_INSERT,
            QueryInterface::ACTION_UPDATE,
            QueryInterface::ACTION_DELETE,
            QueryInterface::ACTION_REPLACE
                                    => new SQLiteQueryExecutor(),
            default                 => null
        };
    }

    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        // All supported functions are pure by Sqlite
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
                $function->resolveSelf();
                break;
        }
    }
}
