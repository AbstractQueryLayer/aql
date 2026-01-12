<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinition;

interface GenerateColumnAfterInterface
{
    public function generateColumnDefinitionAfter(ColumnDefinition $column): ColumnDefinition|array;
}
