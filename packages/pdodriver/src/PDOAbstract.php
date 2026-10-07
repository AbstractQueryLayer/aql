<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\SqlDriver\SqlDriverAbstract;
use IfCastle\AQL\Storage\Exceptions\ConnectFailed;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\ServerHasGoneAwayException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SqlStatementInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\DI\Exceptions\ConfigException;
use IfCastle\Exceptions\UnexpectedValueType;

abstract class PDOAbstract extends SqlDriverAbstract
{
    protected ?\PDO $dbh = null;

    /**
     * @throws ConfigException
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->options              = $this->defineOptions($this->options);
    }

    /**
     * Returns the PDO options the connection is opened with, given the configured ones.
     *
     * @param array<int, mixed> $options
     * @return array<int, mixed>
     * @throws ConfigException when persistent connections are asked for, or the options cannot work together
     */
    protected function defineOptions(array $options): array
    {
        // TrueAsync forbids them: a persistent connection belongs to the process and outlives the
        // request, and one that served a coroutine crashes PHP at shutdown.
        if (!empty($options[\PDO::ATTR_PERSISTENT])) {
            throw new ConfigException('PDO::ATTR_PERSISTENT is not supported under TrueAsync');
        }

        return $options;
    }

    /**
     * @throws ConnectFailed
     */
    #[\Override]
    protected function connectionAttempt(): void
    {
        try {
            $this->dbh              = new \PDO($this->dsn, $this->username, $this->password, $this->options);
        } catch (\PDOException $pdoException) {
            $this->telemetry?->registerError($this, $pdoException);
            throw (new ConnectFailed($pdoException->getMessage(), 0, $pdoException))
                ->appendData($pdoException->errorInfo ?? []);
        }

        $this->telemetry?->registerConnect($this);
    }

    /**
     * @throws ConnectFailed
     * @throws RecoverableException
     * @throws DuplicateKeysException
     * @throws QueryException
     * @throws ServerHasGoneAwayException
     * @throws StorageException
     */
    #[\Override]
    protected function realExecuteQuery(string $sql): ResultInterface
    {
        try {

            return new PDOResult($this->dbh->query($sql));

        } catch (\PDOException $pdoException) {

            throw $this->queryFailed($pdoException, $sql);
        }
    }

    #[\Override]
    protected function realCreateStatement(string $sql): SqlStatementInterface
    {
        try {
            $statement                  = $this->dbh->prepare($sql);
        } catch (\PDOException $pdoException) {
            throw $this->queryFailed($pdoException, $sql);
        }

        return new PDOStatementAdapter($statement);
    }

    #[\Override]
    protected function realExecuteStatement(SqlStatementInterface $statement): ResultInterface
    {
        if (false === $statement instanceof PDOStatementAdapter) {
            throw new UnexpectedValueType('$statement', $statement, PDOStatementAdapter::class);
        }

        try {
            $statement->getPdoStatement()->execute($parameters);
        } catch (\PDOException $pdoException) {
            throw $this->queryFailed($pdoException, $statement->getQuery());
        }

        return new PDOResult($statement->getPdoStatement());
    }

    /**
     * Returns the storage exception for a failed query. On a lost connection an unpooled PDO is dropped,
     * so that the next query reconnects.
     */
    protected function queryFailed(\PDOException $pdoException, string $sql): StorageException
    {
        $exception                  = $this->normalizeException($pdoException, $sql);

        if ($exception instanceof ServerHasGoneAwayException && false === $this->isPooled()) {
            $this->dbh              = null;
        }

        return $exception;
    }

    /**
     * Whether the PDO runs the TrueAsync connection pool. A pool evicts a broken connection itself, and
     * dropping the pooled PDO would close the connections of every other coroutine.
     */
    protected function isPooled(): bool
    {
        return false;
    }

    /**
     * Closes the connection, and under the pool every pooled connection; the next query opens it again.
     */
    #[\Override]
    public function disconnect(): void
    {
        $this->dbh                  = null;
        parent::disconnect();
    }

    #[\Override]
    protected function isDisconnected(): bool
    {
        return $this->dbh === null;
    }

    /**
     * Begins through PDO, which keeps a pooled connection with the coroutine until the transaction ends.
     *
     * @throws StorageException when the transaction names an isolation level: this driver cannot set one
     */
    #[\Override]
    protected function realBeginTransaction(TransactionInterface $transaction): void
    {
        if ($transaction->getIsolationLevel() !== null) {
            throw new QueryException(static::class . ' cannot set a transaction isolation level', 'BEGIN');
        }

        $this->transactionCall($this->dbh->beginTransaction(...), 'BEGIN');
    }

    #[\Override]
    protected function realQuote(string $value): string
    {
        return $this->dbh->quote($value);
    }

    #[\Override]
    protected function realLastInsertId(): mixed
    {
        return $this->dbh->lastInsertId();
    }

    #[\Override]
    protected function realCommit(): void
    {
        $this->transactionCall($this->dbh->commit(...), 'COMMIT');
    }

    #[\Override]
    protected function realInTransaction(): bool
    {
        return $this->dbh->inTransaction();
    }

    #[\Override]
    protected function realRollback(): void
    {
        $this->transactionCall($this->dbh->rollBack(...), 'ROLLBACK');
    }

    /**
     * Runs a transaction method of PDO, reporting its failure as a storage exception.
     *
     * @throws StorageException
     */
    protected function transactionCall(callable $call, string $sql): void
    {
        try {
            if ($call() === false) {
                throw new QueryException('PDO refused to finish the transaction operation', $sql);
            }
        } catch (\PDOException $pdoException) {
            throw $this->queryFailed($pdoException, $sql);
        }
    }
}
