<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Conditions;

use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantTupleInterface;

/**
 * Conditions with a tuple of columns.
 * Such an expression is any expression that has a tuple of columns on the left side
 * and a tuple of constant values on the right side.
 *
 * Example:
 * column = value
 * (column1, column2) = (value1, value2)
 * (column1 = value1 OR column2 = value1)
 * etc...
 *
 * Such expressions can be converted into valid SQL expressions with values on the right side.
 *
 */
interface TupleConditionsInterface extends ConditionsInterface
{
    /**
     * Returns left side columns.
     *
     * @return ColumnInterface[]
     */
    public function getLeftColumns(): array;

    /**
     * @return ColumnInterface[]
     */
    public function getRightColumns(): array;

    /**
     *
     *
     *
     * @return $this
     */
    public function substituteRightExpression(ConstantInterface|ConstantTupleInterface $constantExpression): static;
}
