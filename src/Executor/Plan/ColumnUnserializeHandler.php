<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;

readonly class ColumnUnserializeHandler implements RowModifierInterface
{
    public function __construct(public TupleColumnInterface $tupleColumn, public PropertyInterface $property) {}

    #[\Override]
    public function modifyResultRows(array &$rows, ExecutionContextInterface $context): void
    {
        $alias                      = $this->tupleColumn->getAliasOrColumnName();

        foreach ($rows as &$row) {

            if (\array_key_exists($alias, $row)) {
                $row[$alias]        = $this->property->propertyUnSerialize($row[$alias], $context);
            }

        }

        unset($row);
    }

    #[\Override]
    public function __invoke(...$args): null
    {
        $this->modifyResultRows(...$args);
        return null;
    }
}
