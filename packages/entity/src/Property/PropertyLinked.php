<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\NullNode;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;

/**
 * Allows you to declare a virtual property that is actually defined in another entity.
 * The entity must depend on the current.
 * Example: Portal and portal settings.
 * The property is readable and writable.
 *
 * @package IfCastle\AQL\Dsl\Entity\Properties
 */
class PropertyLinked extends VirtualProperty
{
    public function __construct(string $name, protected string $ownsEntityName, protected ?string $ownsPropertyName = null, protected array $insertValues = [])
    {
        parent::__construct($name, '');
    }

    protected function handleTuple(PropertyContextInterface $context): bool
    {
        [$ownsEntity, $property]    = $this->defineOwnPropertyAndEntity($context);

        $context->getFieldRef()->setSubstitution(new FieldRef($property->getName(), new Subject($ownsEntity->getName())));

        return true;
    }

    protected function handleFilter(PropertyContextInterface $context): bool
    {
        [$ownsEntity, $property]    = $this->defineOwnPropertyAndEntity($context);

        $context->getFieldRef()->setSubstitution(new FieldRef($property->getName(), new Subject($ownsEntity->getName())));

        return true;
    }

    protected function handleAssign(PropertyContextInterface $context): bool
    {
        [$ownsEntity, $property]    = $this->defineOwnPropertyAndEntity($context);

        $mainEntityName             = $context->getMainEntity()->getEntityName();
        $propertyOwnedName          = $ownsEntity->getName();

        $relations                  = $ownsEntity->getRelations($mainEntityName);

        if ($relations === null) {
            throw new TransformationException(\sprintf('Relations is NULL between %s and %s', $mainEntityName, $propertyOwnedName));
        }

        $foreignKeyName             = $relations->getForeignFieldRef()->getName();
        $relations                  = clone $relations;

        $propertyQuery              = $this->buildQuery($ownsEntity, $property, $context, $relations);

        // Substitute to empty expression for assign
        // (remove property)
        $context->getExpression()->setSubstitution(new NullNode());

        $context->beginTransaction();

        $context->addAfterHandler(static function () use ($context, $propertyQuery, $ownsEntity, $foreignKeyName) {
            $primaryKeyValue    = $context->findPrimaryKeyValue();
            if ($primaryKeyValue === null) {
                throw new ExpressionException('primary key value is undefined');
            }

            if ($propertyQuery instanceof Insert) {
                $propertyQuery->addAssign(new FieldRef($ownsEntity->getPrimaryKeyName()), new Variable($primaryKeyValue, 'primaryKey'));
            } else {

                //
                // For update query we set foreignKey value
                //

                $filter             = $propertyQuery->getWhere()->findPropertyFilter($foreignKeyName);

                if ($filter === null) {
                    throw new ExpressionException(\sprintf('Filter for \'%s\' is undefined', $foreignKeyName));
                }

                if ($filter->getRightExpression() instanceof ConstantAbstract) {
                    $filter->getRightExpression()->setValue($primaryKeyValue);
                } else {
                    throw new ExpressionException(\sprintf('Right side for filter \'%s\' is not a constant', $foreignKeyName));
                }
            }
        });

        $context->addQueryAfter($propertyQuery);

        return true;
    }

    protected function defineOwnPropertyAndEntity(PropertyContextInterface $context): array
    {
        $ownsEntity                 = $context->getEntity($this->ownsEntityName);

        $property                   = $ownsEntity->getProperty($this->ownsPropertyName ?? $this->name);

        if ($property === null) {
            throw new RuntimeException(
                'LinkedProperty tries to refer to a non-existent property: '
                . \sprintf('\'%s\' for entity \'%s\'', $this->name, $ownsEntity->getName())
            );
        }

        return [$ownsEntity, $property];
    }

    protected function buildQuery(EntityI $ownsEntity, Property $property, PropertyContextInterface $context, RelationInterface $relations): Update
    {
        /**
         * Restriction: INSERT query is disabled by default, unless otherwise specified.
         *
         */
        $isInsert                   = $context->getQuery()->getAction() === QueryAbstract::ACTION_INSERT;

        if ($isInsert && $this->insertValues === []) {
            throw new ExpressionException("Linked property can't use with INSERT query", [
                'property'          => $this->name,
                'ownsEntity'        => $ownsEntity->getName(),
            ]);
        }

        /**
         * Restriction for UPDATE:
         * Changing the associated property is only allowed if the primary key is specified
         * The reason for this limitation is that it is
         * not possible to compute the record to update in a direct way if the key is not known.
         */
        if (false === $isInsert && $context->findPrimaryKeyFilter() === null) {
            throw new ExpressionException("Linked property can't use without primary key filter", [
                'property'          => $this->name,
                'ownsEntity'        => $ownsEntity->getName(),
            ]);
        }

        if ($isInsert) {
            $query                  = new Insert(new Subject($ownsEntity->getName()), $this->generateAssignsForInsert());
        } else {
            $query                      = new Update(new Subject($ownsEntity->getName()), new Where()->add($relations->getConditions()));
            $query->getAssigns()->add(new FieldRef($property->getName()), $context->getRightConstant());
        }

        return $query;
    }

    protected function generateAssignsForInsert(): Assigns
    {
        $assigns                        = new Assigns();

        foreach ($this->insertValues as $key => $value) {

            if (\is_scalar($value) && \is_string($key)) {
                $assigns->add($key, $value);
            } elseif ($value instanceof Assign) {
                $assigns->add($value);
            } else {
                throw new ExpressionException('insertValues has wrong type', ['type' => \gettype($value)]);
            }

        }

        return $assigns;
    }

}
