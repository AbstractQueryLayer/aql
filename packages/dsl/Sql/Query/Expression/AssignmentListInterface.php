<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;

/**
 * List of expressions:
 * left side := right side
 *
 */
interface AssignmentListInterface extends NodeInterface, ChildNodeMutableInterface
{
    public function isValueSyntax(): bool;

    /**
     * Specifies to translate SQL into a VALUES() expression.
     * Use this method if you need to support databases that do not support the SET syntax.
     *
     * @return $this
     */
    public function asValueSyntax(): static;

    public function isListEmpty(): bool;

    public function isListNotEmpty(): bool;

    public function addAssign(Assign $assign): static;

    public function addAssignment(string|ColumnInterface $column, NodeInterface $rightSide): static;

    public function assign(string $column, int|bool|null|float|string $value): static;

    public function findAssignByColumn(string|ColumnInterface $column): ?Assign;

    /**
     *
     * @return  array<string, array<ColumnInterface|null>>
     */
    public function findRightNodes(string|ColumnInterface ...$columns): array;
}
