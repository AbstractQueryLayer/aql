<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

/**
 * Column Multiple parameter these are reflections of expressions with Row Constructor:
 *
 * (column1, column2) <operation> (column1, column2)
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/row-constructor-optimization.html
 */
interface ConstantTupleInterface extends ConstantInterface
{
    /**
     * @return string[]
     */
    public function getTupleColumns(): array;

    /**
     * @return string[]
     */
    public function getTupleColumnTypes(): ?array;
}
