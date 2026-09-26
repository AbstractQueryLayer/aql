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
use IfCastle\AQL\Transaction\IsolationLevelEnum;
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

    #[\Override]
    protected function isDisconnected(): bool
    {
        return $this->dbh === null;
    }

    #[\Override]
    protected function realBeginTransaction(TransactionInterface $transaction): void
    {
        if ($transaction->getParentTransaction() === null) {

            $isolationLevel         = match ($transaction->getIsolationLevel()) {
                null                => null,
                IsolationLevelEnum::UNCOMMITTED  => 'READ UNCOMMITTED',
                IsolationLevelEnum::COMMITTED    => 'READ COMMITTED',
                IsolationLevelEnum::REPEATABLE   => 'REPEATABLE READ',
                IsolationLevelEnum::SERIALIZABLE => 'SERIALIZABLE',
            };

            if ($isolationLevel !== null) {
                $this->dbh->exec('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolationLevel . '; START TRANSACTION');
            } else {
                $this->dbh->beginTransaction();
            }
        }

        if ($transaction->getTransactionId() === null) {
            $transaction->setTransactionId('t' . \spl_object_id($transaction));
        }

        //
        // See: https://dev.mysql.com/doc/refman/9.0/en/savepoint.html
        //
        $this->dbh->exec('SAVEPOINT ' . $transaction->getTransactionId());
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
    protected function realCommit(TransactionInterface $transaction): void
    {
        if ($transaction->getTransactionId() === null) {
            $this->dbh->commit();
            return;
        }

        //
        // See: https://dev.mysql.com/doc/refman/9.0/en/savepoint.html
        //
        $this->dbh->exec('RELEASE SAVEPOINT ' . $transaction->getTransactionId());
    }

    #[\Override]
    protected function realRollback(TransactionInterface $transaction): void
    {
        if ($transaction->getParentTransaction()?->getTransactionId() === null) {
            $this->dbh->rollBack();
            return;
        }

        //
        // See: https://dev.mysql.com/doc/refman/9.0/en/savepoint.html
        //
        $this->dbh->exec('ROLLBACK TO SAVEPOINT ' . $transaction->getParentTransaction()->getTransactionId());
    }
}
