<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;

interface ColumnListInterface extends ChildNodeMutableInterface
{
    public function getColumns(): array;

    public function findColumn(string|ColumnInterface $name): ?ColumnInterface;

    public function findColumnOffset(string|ColumnInterface $name): ?int;

    public function appendColumn(ColumnInterface|string $column): static;
}
