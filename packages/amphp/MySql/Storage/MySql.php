<?php

declare(strict_types=1);

namespace IfCastle\AQL\Amphp\MySql\Storage;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\SocketMysqlConnection;
use Amp\Socket;
use Amp\Sql\SqlException;
use IfCastle\AQL\Amphp\SqlResultAdapter;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\SqlDriver\SqlDriverAbstract;
use IfCastle\AQL\Storage\Exceptions\ConnectFailed;
use IfCastle\AQL\Storage\Exceptions\ServerHasGoneAwayException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SqlStatementInterface;
use IfCastle\AQL\Transaction\TransactionInterface;

class MySql extends SqlDriverAbstract
{
    private MysqlConfig|null           $mysqlConfig = null;

    private SocketMysqlConnection|null $connection  = null;

    private int|null $lastInsertId = null;

    private function defineMySqlConfig(): void
    {
        $this->mysqlConfig      = MysqlConfig::fromAuthority($this->dsn, $this->username, $this->password);
    }

    #[\Override]
    protected function connectionAttempt(): void
    {
        $this->defineMysqlConfig();

        try {
            $this->connection = SocketMysqlConnection::connect(Socket\socketConnector(), $this->mysqlConfig);
        } catch (SqlException $exception) {
            $this->telemetry?->registerError($this, $exception);
            throw new ConnectFailed($exception->getMessage(), 0, $exception);
        }
    }

    #[\Override]
    protected function realExecuteQuery(string $sql): ResultInterface
    {
        try {

            $result                 = $this->connection->query($sql);
            $this->lastInsertId     = $result->getLastInsertId();

            return new SqlResultAdapter($result);

        } catch (SqlException $exception) {

            $exception              = $this->normalizeException($exception, $sql);

            if ($exception instanceof ServerHasGoneAwayException) {
                $this->connection  = null;
            }

            throw $exception;
        }
    }

    #[\Override]
    protected function isDisconnected(): bool
    {
        return $this->connection === null;
    }

    #[\Override]
    protected function realCreateStatement(string $sql): SqlStatementInterface
    {
        // TODO: Implement realCreateStatement() method.
    }

    #[\Override]
    protected function realExecuteStatement(SqlStatementInterface $statement): ResultInterface
    {
        // TODO: Implement realExecuteStatement() method.
    }

    #[\Override]
    protected function realBeginTransaction(TransactionInterface $transaction): void {}

    #[\Override]
    protected function realQuote(string $value): string
    {
        //
        // The SERVER_STATUS_NO_BACKSLASH_ESCAPES mode is available in the MysqlConnectionMetadata,
        // but in the current version, it cannot be retrieved, so we use the classic ESCAPE version for strings.
        // @see https://github.com/mariadb-corporation/mariadb-connector-c/blob/3.4/libmariadb/ma_charset.c#L1155
        //
        return \str_replace(["\0", "\n", "\r", '\\', '\'', '"', "\032"], ['\\0', '\\n', '\\r', '\\\\', "\\'", '\\"', '\\Z'], $value);
    }

    #[\Override]
    protected function realLastInsertId(): mixed
    {
        return $this->lastInsertId;
    }

    #[\Override]
    protected function realCommit(TransactionInterface $transaction): void
    {
        $this->connection->execute('COMMIT');
    }

    #[\Override]
    protected function realRollback(TransactionInterface $transaction): void
    {
        $this->connection->execute('ROLLBACK');
    }

    #[\Override]
    protected function normalizeException(\Throwable $exception, string $sql): StorageException
    {
        // TODO: Implement normalizeException() method.
    }

    #[\Override]
    protected function isNestedTransactionsSupported(): bool
    {
        return true;
    }

    #[\Override]
    public function newEntityToTableGenerator(EntityInterface $entity): EntityToTableInterface {}
}
