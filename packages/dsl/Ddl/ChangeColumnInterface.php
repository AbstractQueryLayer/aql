<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface ChangeColumnInterface extends NodeInterface
{
    final public const string OLD_NAME      = 'oldColumnName';

    final public const string DEFINITION    = 'columnDefinition';

    public function getOldColumnName(): string;

    public function getColumnDefinition(): ColumnDefinitionInterface;
}
