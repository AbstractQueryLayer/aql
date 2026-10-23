<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Result\ResultInterface;

interface SqlStatementFactoryInterface
{
    public function createStatement(string $sql, ?object $context = null): SqlStatementInterface;

    public function executeStatement(SqlStatementInterface $statement, ?object $context = null): ResultInterface;
}
