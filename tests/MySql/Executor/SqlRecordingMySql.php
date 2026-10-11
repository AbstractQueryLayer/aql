<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Result\ResultInterface;

/** MySql storage that keeps the last SQL it executed, so a test can EXPLAIN what AQL generated. */
class SqlRecordingMySql extends MySql
{
    public ?string $lastSql         = null;

    #[\Override]
    public function executeSql(string $sql, ?object $context = null): ResultInterface
    {
        $this->lastSql              = $sql;

        return parent::executeSql($sql, $context);
    }
}
