<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface ColumnHandlerInterface
{
    /**
     * Handles a reference to a Database Field.
     */
    public function handleColumn(ColumnInterface $column, NodeContextInterface $context): void;
}
