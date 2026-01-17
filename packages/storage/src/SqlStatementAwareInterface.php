<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface SqlStatementAwareInterface
{
    public function getSqlStatement(): SqlStatementInterface|null;

    public function setSqlStatement(SqlStatementInterface $sqlStatement): void;
}
