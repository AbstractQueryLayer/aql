<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;

/**
 * Property Resolution Strategy for DerivedEntity based on the properties requested
 * in the Subquery from which derivedEntity was formed.
 *
 * This strategy will copy all properties from the subquery,
 * making them available in the derived entity.
 * If a property was absent in the subquery, even if it exists in the original entity, an error will occur.
 *
 * If the subquery uses the "SELECT * FROM Entity" syntax, all properties of the original entity will be available.
 */
class PropertyResolverIfExistsInSubquery implements PropertyResolverInterface
{
    protected \WeakReference|null $entityFactory = null;

    public function __construct(
        protected readonly SubqueryInterface $subquery,
        protected readonly EntityInterface $originalEntity,
        EntityFactoryInterface $entityFactory
    ) {
        $this->entityFactory        = \WeakReference::create($entityFactory);
    }

    #[\Override]
    public function resolveAllProperties(): array
    {
        $tuple                      = $this->subquery->getTuple();

        if ($tuple === null) {
            return [];
        }

        $entity                     = $this->originalEntity;

        if ($tuple->isDefaultColumns()) {

            $properties             = [];

            foreach ($entity->getDefaultColumns() as $column) {
                $properties[$column] = $this->deriveProperty($entity->getProperty($column));
            }

            return $properties;
        }

        $properties                 = [];
        $index                      = 0;

        foreach ($tuple->getTupleColumns() as $tupleColumn) {
            $index++;
            $propertyName           = $tupleColumn->getAliasOrColumnNameOrNull() ?? 'column' . $index;
            $properties[$propertyName] = $this->tupleColumnToProperty($propertyName, $tupleColumn);
        }

        return $properties;
    }

    #[\Override]
    public function resolveProperty(string $propertyName, ?string $entityName = null): PropertyInterface|null
    {
        // Try to find property in the main entity if subquery tuple use default columns
        if ($this->subquery->getTuple()->whetherDefault()) {
            return $this->deriveProperty($this->originalEntity->getProperty($propertyName, false));
        }

        // Try to find property as alias in tuple
        return $this->tupleColumnToProperty($propertyName, $this->subquery->getTuple()->findTupleColumn($propertyName));
    }

    protected function tupleColumnToProperty(string $propertyName, ?TupleColumnInterface $tupleColumn = null): PropertyInterface|null
    {
        if ($tupleColumn === null && false === $this->resolveNonExistProperty($propertyName)) {
            return null;
        }

        if ($tupleColumn === null) {
            $tupleColumn            = $this->subquery->getTuple()->findTupleColumn($propertyName);
        }

        if ($tupleColumn === null) {
            return null;
        }

        $expression                 = $tupleColumn->getExpression();

        if ($expression instanceof ColumnInterface) {

            if ($expression->getEntityName() === null || $expression->getEntityName() === $this->originalEntity->getEntityName()) {
                return $this->deriveProperty($this->originalEntity->getProperty($expression->getColumnName(), false));
            }

            return $this->deriveProperty(
                $this->entityFactory?->get()->getEntity($expression->getEntityName())->getProperty($expression->getColumnName(), false)
            );
        }

        if ($expression instanceof SubqueryInterface || $expression instanceof FunctionReferenceInterface) {
            return new DerivedPropertyAsExpression($propertyName);
        }

        return null;
    }

    /**
     * Return true if property was resolved,
     * i.e., property was added to the subquery dynamically.
     *
     */
    protected function resolveNonExistProperty(string $propertyName): bool
    {
        return false;
    }

    protected function deriveProperty(?PropertyInterface $property = null, string $name = ''): PropertyInterface|null
    {
        if ($property === null) {
            return null;
        }

        return new DerivedProperty($property, $name);
    }
}
