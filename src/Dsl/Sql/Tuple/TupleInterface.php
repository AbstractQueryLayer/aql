<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;

interface TupleInterface extends NodeInterface, ChildNodeMutableInterface
{
    public const string NODE_COLUMNS          = 'c';

    public const string NODE_HIDDEN_COLUMNS   = 'h';

    /**
     * Whether a tuple is a default list of columns.
     */
    public function whetherDefault(): bool;

    /**
     * @return TupleColumnInterface[]
     */
    public function getTupleColumns(): array;

    public function findTupleColumn(string $alias): ?TupleColumnInterface;

    public function getHiddenColumns(): array;

    /**
     * Add new expression to columns.
     *
     *
     * @return $this
     */
    public function addTupleColumn(TupleColumnInterface|NodeInterface $tupleColumn, ?string $alias = null): static;

    /**
     * Add a column if it does not exist.
     *
     *
     */
    public function addColumnIfNoExists(TupleColumnInterface $newColumn): TupleColumnInterface;

    /**
     * Add a new column if it does not exist by alias.
     * Method will return existing column if it has the same FieldRef or null if it does not exist.
     *
     *
     */
    public function addColumnIfNoExistsByAlias(TupleColumnInterface $newColumn): TupleColumnInterface|null;

    public function addHiddenColumn(TupleColumnInterface|NodeInterface $tupleColumn, ?string $alias = null): static;

    /**
     * The method creates or finds a hidden tuple column for the specified entity property.
     * The method will not duplicate hidden columns if they are already defined before.
     *
     *
     */
    public function resolveHiddenColumn(ColumnInterface $column, ?callable $aliasGenerator = null): TupleColumnInterface;

    /**
     * Special condition for SELECT * FROM statement.
     */
    public function isDefaultColumns(): bool;

    public function markAsDefaultColumns(): static;

    public function generateAliasForHiddenColumn(): string;
}
