<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\SqlStatementInterface;

/**
 * MySql storage that keeps the last SQL it executed, so a test can EXPLAIN what AQL generated, and the SQL of the
 * last statement it prepared, with its placeholders.
 */
class SqlRecordingMySql extends MySql
{
    public ?string $lastSql         = null;

    public ?string $lastStatementSql = null;

    #[\Override]
    public function executeSql(string $sql, ?object $context = null): ResultInterface
    {
        $this->lastSql              = $sql;

        return parent::executeSql($sql, $context);
    }

    #[\Override]
    public function createStatement(string $sql, ?object $context = null): SqlStatementInterface
    {
        $this->lastStatementSql     = $sql;

        return parent::createStatement($sql, $context);
    }
}
