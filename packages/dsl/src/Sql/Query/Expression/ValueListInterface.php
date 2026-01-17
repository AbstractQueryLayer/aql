<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;

interface ValueListInterface extends ColumnListInterface, NodeInterface
{
    public const string OPERATION_VALUES = 'VALUES';

    public const string OPERATION_IN = 'IN';

    public const string OPERATION_NOT_IN = 'NOT IN';

    public const string NODE_COLUMNS    = 'c';

    public const string NODE_VALUES     = 'v';

    public function setOperation(string $operation): static;

    public function asIn(): static;

    public function asNotIn(): static;

    public function isListEmpty(): bool;

    public function isListNotEmpty(): bool;

    /**
     * The method returns an array of tuples for the specified columns.
     * A tuple is an array with keys, where the key is equal to the column name.
     *
     *
     * @return  array<string, NodeInterface>
     */
    public function findValues(string|ColumnInterface ...$columns): array;

    /**
     * The method iterates over all values in the Values list for the specified columns.
     * The method returns:
     * [array<string, NodeI> $results, NodeList $set].
     *
     *
     */
    public function walkValues(string|ColumnInterface ...$columns): \Iterator;

    public function defineValues(array $values): static;

    public function appendValueList(array|NodeList $valueList): static;

    public function appendColumnToSet(ColumnInterface|string $column, ConstantInterface $value): static;

    /**
     *
     * @return  $this
     */
    public function appendColumnWithGenerator(ColumnInterface|string $column, callable $valueGenerator): static;
}
