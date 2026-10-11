<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\InExpression;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;

/**
 * A fixed-length column of raw bytes, BINARY(n).
 *
 * The values given for the column are written into SQL as binary literals of the storage, so a value may hold any
 * bytes, NUL and sequences invalid in the connection charset included. Covered: the right side of an operation
 * (`column = v`, `SET column = v`, `column IN (v1, v2)`) and the column's values in `(…) VALUES (…)`. A value on the
 * left side (`v = column`) stays a text literal. Values are read back as PHP strings.
 */
class PropertyBinary extends PropertyAbstract
{
    /**
     * @param int $length the column length in bytes; a value of another length fails validation
     */
    public function __construct(string $name, int $length, bool $isNullable = false)
    {
        parent::__construct($name, self::T_BINARY, $isNullable);
        $this->minLength            = $length;
        $this->maxLength            = $length;
    }

    #[\Override]
    protected function handleBefore(PropertyContextInterface $context, string $contextName): void
    {
        parent::handleBefore($context, $contextName);

        foreach ($this->valuesOf($context->getCurrentNode()) as $value) {
            if ($value instanceof Constant) {
                $value->asBinary();
            }
        }
    }

    /**
     * The nodes that give values to $column in its expression.
     *
     * @return iterable<NodeInterface>
     */
    private function valuesOf(NodeInterface $column): iterable
    {
        $parent                     = $column->getParentNode();
        $valueList                  = $parent?->getParentNode();

        // In a value list the column is a child of the column list, and each row has its value at the same offset.
        if ($valueList instanceof ValueListInterface && $column instanceof ColumnInterface) {
            return $valueList->findValues($column)[$column->getColumnName()] ?? [];
        }

        if (false === $parent instanceof LROperationInterface || $parent->getLeftNode() !== $column) {
            return [];
        }

        $right                      = $parent->getRightNode();

        if ($right instanceof InExpression) {
            return $right->getChildNodes();
        }

        return $right === null ? [] : [$right];
    }
}
