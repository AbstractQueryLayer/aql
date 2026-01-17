<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Column;

/**
 * Column Multiple parameter these are reflections of expressions with Row Constructor:
 *
 * (column1, column2) <operation> (column1, column2)
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/row-constructor-optimization.html
 */
interface ColumnsTupleInterface extends ColumnInterface
{
    /**
     * @return ColumnInterface[]
     */
    public function getTupleColumns(): array;
}
