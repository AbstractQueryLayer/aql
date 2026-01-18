<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Generator;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Generator\Ddl\EntityToTableAbstract;

/**
 * ## EntityToTable.
 *
 * A generator that turns an entity into a DDL structure.
 */
class EntityToTable extends EntityToTableAbstract
{
    #[\Override]
    protected function propertyToColumn(PropertyInterface $property): ColumnDefinitionInterface|array
    {
        return new PropertyToColumn($this->entity, $this->entityFactory, $property)->generate();
    }
}
