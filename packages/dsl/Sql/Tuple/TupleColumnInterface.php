<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface TupleColumnInterface extends NodeInterface
{
    public function getExpression(): NodeInterface;

    public function getAlias(): ?string;

    public function getAliasOrColumnNameOrNull(): ?string;

    public function getAliasOrColumnName(): string;

    public function setAliasIfUndefined(string $alias): static;

    public function markAsPlaceholder(): static;

    public function isColumnEqual(TupleColumnInterface $column): bool;
}
