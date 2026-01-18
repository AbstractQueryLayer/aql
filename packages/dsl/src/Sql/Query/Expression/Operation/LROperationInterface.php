<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;

/**
 * LROperationInterface.
 *
 * Interface for operations that have a left and a right side, such as X equals Y, or A > B.
 * Such operations typically consist of columns and constant expressions.
 *
 * LeftKey and ForeignKey are columns with opposite meanings.
 * LeftKey is the left key, which always refers to the current entity.
 * ForeignKey always refers to an external column.
 */
interface LROperationInterface extends OperationInterface
{
    public const string EQU         = '=';

    public const string ASSIGN      = ':=';

    public const string NOT_EQU     = '!=';

    public const string GREATER     = '>';

    public const string LESS        = '<';

    public const string GREATER_EQU = '>=';

    public const string LESS_EQU    = '<=';

    public const string DIVIDE      = '/';

    public const string MULTIPLY    = '*';

    public const string ADD         = '+';

    public const string MINUS       = '-';

    public const string IN          = 'IN';

    public const string NOT_IN      = 'NOT IN';

    public const string LIKE        = 'LIKE';

    public const string NOT_LIKE    = 'NOT LIKE';

    public const string IS          = 'IS';

    public const string IS_NOT      = 'IS NOT';

    public const string LEFT        = 'left';

    public const string RIGHT       = 'right';

    /**
     * The method will return TRUE if the left side of the operation is a tuple of columns.
     *
     */
    public function isCompositeComparison(): bool;

    public function getLeftNode(): ?NodeInterface;

    public function getRightNode(): ?NodeInterface;

    /**
     * The method can only be used for non-composite operations.
     *
     * Attention!
     * If the LROperation is a composite comparison with a tuple of columns,
     * this method will still return NULL, not the first column as one might expect.
     *
     */
    public function getLeftKey(): ?ColumnInterface;

    /**
     *
     * The method can only be used for non-composite operations.
     *
     * Attention!
     * If the LROperation is a composite comparison with a tuple of columns,
     * this method will still return NULL, not the first column as one might expect.
     *
     */
    public function getForeignKey(): ?ColumnInterface;

    /**
     * This method works correctly for both composite comparisons and regular comparisons.
     * It will always return an array. If there is a column in the expression, it will return at least one column.
     *
     * @return ColumnInterface[]
     */
    public function getLeftKeys(): array;

    /**
     * This method works correctly for both composite comparisons and regular comparisons.
     * It will always return an array. If there is a column in the expression, it will return at least one column.
     *
     * @return ColumnInterface[]
     */
    public function getForeignKeys(): array;

    public function getConstantValue(): mixed;
}
