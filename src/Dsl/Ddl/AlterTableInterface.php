<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface AlterTableInterface extends NodeInterface
{
    final public const string TABLE_NAME = 'tableName';

    final public const string ALTER_OPTIONS = 'alterOptions';

    final public const string PARTITION_OPTIONS = 'partitionOptions';

    public function getTableName(): string;

    public function setTableName(string $tableName): static;

    public function addAlterOption(AlterOptionInterface $alterOption): static;
}
