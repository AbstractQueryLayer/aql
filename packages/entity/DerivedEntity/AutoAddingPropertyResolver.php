<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;

final class AutoAddingPropertyResolver extends PropertyResolverIfExistsInSubquery
{
    /**
     * @throws QueryException
     */
    #[\Override]
    protected function resolveNonExistProperty(string $propertyName): bool
    {
        $property                   = $this->originalEntity->getProperty($propertyName, false);

        if ($property === null) {
            return false;
        }

        $newColumn                  = new TupleColumn(new Column($property->getName(), $this->originalEntity->getEntityName()));

        $tuple                      = $this->subquery->getTuple();
        $isTransformed              = $tuple->isTransformed();

        $existsColumn               = $tuple->addColumnIfNoExists($newColumn);

        if ($existsColumn !== $newColumn) {
            throw new QueryException([
                'template'          => 'Conflict of column usage in Derived query with {property} property. '
                                     . 'Column {column} already used in CTE expression as {exists} field. Subquery: {subquery}',
                'property'          => $propertyName,
                'column'            => $existsColumn->getAliasOrColumnName(),
                'exists'            => $existsColumn->getAql(),
                'subquery'          => $this->subquery->getAql(),
            ]);
        }

        if ($isTransformed) {
            $context                = $existsColumn->closestParentContext();

            if ($context instanceof NodeContextInterface) {
                $context->transform($existsColumn);
            }
        }

        return true;
    }
}
