<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyInterface;
use IfCastle\AQL\Entity\CrossReferenceEntity;
use IfCastle\AQL\Entity\EntityBuildPlanInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityStorageInterface;
use IfCastle\AQL\Entity\Property\ComplexProperty;
use IfCastle\AQL\Entity\Property\PropertyCrossReference;
use IfCastle\AQL\Entity\Relation\DirectRelation;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Entity\Relation\IndirectRelation;
use IfCastle\AQL\Entity\Relation\IndirectRelationInterface;
use IfCastle\AQL\Entity\Relation\RelationAbstract;
use IfCastle\AQL\Entity\Relation\RelationInheritByInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValue;

class EntityBuilder implements EntityBuilderInterface, DisposableInterface
{
    public function __construct(
        protected EntityAspectBuilderFactoryInterface   $entityAspectBuilderFactory,
        protected EntityFactoryInterface                $entityFactory,
        protected EntityStorageInterface                $entityStorage,
        protected NamingStrategyInterface               $namingStrategy,
        protected ContainerInterface                    $container
    ) {}

    #[\Override]
    public function buildEntity(EntityInterface & EntityDescriptorInterface $entity, bool $isRaw = false): void
    {
        $entity->getBuildPlan()
               ->addAfterActionHandler(EntityBuildPlanInterface::STEP_ASPECTS, fn() => $this->applyAspects($entity))
               ->addAfterActionHandler(EntityBuildPlanInterface::STEP_PROPERTIES, fn() => $this->buildProperties($entity));

        $entity->build($isRaw);
    }

    protected function applyAspects(EntityInterface & EntityDescriptorInterface $entity): void
    {
        foreach ($entity->getEntityAspects() as $aspectDescriptor) {
            $this->entityAspectBuilderFactory
                ->getEntityAspectBuilder($aspectDescriptor)
                ->applyAspect($aspectDescriptor, $entity);
        }
    }

    protected function buildProperties(EntityInterface & EntityDescriptorInterface $entity): void {}

    /**
     * @param list<string> $derivedFrom
     */
    #[\Override]
    public function buildReference(
        EntityDescriptorInterface&EntityInterface $fromEntity,
        (EntityDescriptorInterface&EntityInterface)|string $toEntity,
        string $relationType = RelationInterface::REFERENCE,
        bool $isRequired = true,
        ?string $propertyName = null,
        ?string $role = null,
        string $integrity = RelationInterface::INTEGRITY_DECLARED,
        array $derivedFrom = []
    ): void {
        $toEntity = $toEntity instanceof EntityInterface ? $toEntity : $this->entityFactory->getEntity($toEntity, true);

        ComplexProperty::describeReferenceByKey(
            $toEntity->getPrimaryKey(),
            $toEntity,
            $fromEntity,
            $relationType,
            $isRequired,
            $propertyName,
            $role,
            $integrity,
            $derivedFrom
        );
    }

    #[\Override]
    public function buildRole(
        EntityDescriptorInterface & EntityInterface $fromEntity,
        string                                      $role,
        string                                      $toEntity,
        array                                       $pairs,
        string                                      $integrity,
        array                                       $derivedFrom
    ): void {
        // PHP turns a numeric column name used as a key into an integer.
        $columns                    = \array_map(strval(...), \array_keys($pairs));
        $targetColumns              = \array_values($pairs);

        $this->checkRoleDeclaration($fromEntity, $role, $toEntity, $columns, $integrity);
        // Fully built: the target's own reference columns are needed, and a raw build skips them.
        $target                     = $this->entityFactory->getEntity($toEntity);
        $this->checkRoleTarget($fromEntity, $role, $target, $columns, $targetColumns);

        $relation                   = (new DirectRelation(
            $fromEntity->getEntityName(),
            new Key(...$columns),
            $target->getEntityName(),
            new Key(...$targetColumns),
            self::containsUniqueKey($target, $targetColumns)
                ? RelationInterface::REFERENCE : RelationInterface::COLLECTION
        ))->setRelationName($role)
            ->setIntegrity($integrity, $derivedFrom)
            ->setIsRequired(false)
            ->asJoinedByRoleOnly();

        $fromEntity->describeRelation($relation);
    }

    /**
     * @param list<string> $columns own columns of the role
     *
     * @throws EntityDescriptorException
     */
    protected function checkRoleDeclaration(
        EntityInterface $fromEntity,
        string          $role,
        string          $toEntity,
        array           $columns,
        string          $integrity
    ): void {
        $failure                    = match (true) {
            $integrity === RelationInterface::INTEGRITY_DECLARED
                => 'a declared composite foreign key is not supported yet; describe it as implied',
            $columns === []         => 'it has no column pairs',
            // An entity-name lookup reads a relation named like the entity first (EntityAbstract::findRelation).
            !\ctype_lower($role[0] ?? '') => 'a role name begins with a lowercase letter',
            $fromEntity->getProperty($role, false) !== null => 'it repeats a property name',
            default                 => null,
        };

        if ($failure !== null) {
            $this->throwOnRole($fromEntity, $role, $toEntity, $failure);
        }

        foreach ($columns as $column) {
            if ($fromEntity->getProperty($column, false) === null) {
                $this->throwOnRole($fromEntity, $role, $toEntity, 'the entity has no column ' . $column);
            }
        }
    }

    /**
     * @param list<string> $columns       own columns of the role
     * @param list<string> $targetColumns columns of $target, in pair order
     *
     * @throws EntityDescriptorException
     */
    protected function checkRoleTarget(
        EntityInterface $fromEntity,
        string          $role,
        EntityInterface $target,
        array           $columns,
        array           $targetColumns
    ): void {
        $toEntity                   = $target->getEntityName();

        foreach ($targetColumns as $column) {
            if ($target->getProperty($column, false) === null) {
                $this->throwOnRole($fromEntity, $role, $toEntity, 'the target has no column ' . $column);
            }
        }

        if ($fromEntity->getStorageName() !== $target->getStorageName()) {
            $this->throwOnRole($fromEntity, $role, $toEntity, 'the entities are in different storages');
        }

        $pairs                      = self::sortedPairs($columns, $targetColumns);

        foreach ($fromEntity->getRelations() as $name => $relation) {
            if (!$relation instanceof DirectRelationInterface || $relation->getRightEntityName() !== $toEntity) {
                continue;
            }

            $joined                 = self::sortedPairs(
                $relation->getLeftKey()->getKeyColumns(), $relation->getRightKey()->getKeyColumns()
            );

            if ($joined === $pairs) {
                $this->throwOnRole($fromEntity, $role, $toEntity, 'role ' . $name . ' already joins these columns');
            }
        }
    }

    /**
     * @param list<string> $columns
     * @param list<string> $targetColumns
     * @return list<string> "column=targetColumn", sorted: the order of pairs does not change the join
     */
    protected static function sortedPairs(array $columns, array $targetColumns): array
    {
        $pairs                      = \array_map(
            static fn(string $column, string $target): string => $column . '=' . $target, $columns, $targetColumns
        );
        \sort($pairs);

        return $pairs;
    }

    /**
     * True when $columns contain every column of the entity's primary key or of one of its unique keys.
     *
     * @param list<string> $columns
     */
    protected static function containsUniqueKey(EntityInterface $entity, array $columns): bool
    {
        foreach ($entity->getKeys() as $key) {
            if (($key->isPrimary() || $key->isUnique()) && \array_diff($key->getKeyColumns(), $columns) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function throwOnRole(EntityInterface $fromEntity, string $role, string $toEntity, string $failure): never
    {
        throw new EntityDescriptorException([
            'template'              => 'Role {entity}.{role} to {target} cannot be built: {failure}',
            'entity'                => $fromEntity->getEntityName(),
            'role'                  => $role,
            'target'                => $toEntity,
            'failure'               => $failure,
        ]);
    }

    /**
     * @throws UnexpectedValue
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function buildCrossReference(
        EntityInterface&EntityDescriptorInterface          $fromEntity,
        (EntityInterface&EntityDescriptorInterface)|string $toEntity,
        bool                                               $isRequired            = true,
        ?string                                             $throughEntity       = null,
        string                                             $relationType        = RelationInterface::REFERENCE
    ): void {
        $toEntity                   = $toEntity instanceof EntityInterface
                                    ? $toEntity : $this->entityFactory->getEntity($toEntity, true);

        $crossReferenceEntity       = $this->entityFactory->findEntity(
            $throughEntity ?? $this->namingStrategy->generateCrossReferenceEntityName($fromEntity, $toEntity),
            true
        );

        if ($crossReferenceEntity === null) {
            $crossReferenceEntity   = $this->buildCrossReferenceEntity($fromEntity, $toEntity, $relationType, $isRequired);
            $this->entityStorage->setEntity($crossReferenceEntity);
        }

        $crossReferenceRelation     = $crossReferenceEntity->getRelation($fromEntity->getEntityName());

        // Add relations to the cross-reference entity
        $indirectRelation           = new IndirectRelation(
            $fromEntity->getEntityName(),
            $crossReferenceEntity->getEntityName(),
            $toEntity->getEntityName()
        );

        $indirectRelation->setIsRequired($isRequired);

        $fromEntity->describeRelation($indirectRelation);
        // A reversed relation is never required by itself; the hop to the junction follows the cross reference.
        $fromEntity->describeRelation($crossReferenceRelation->reverseRelation()->setIsRequired($isRequired));

        $fromEntity->describeProperty(new PropertyCrossReference(
            $this->namingStrategy->generatePropertyName([$toEntity->getEntityName(), 'list']),
            $toEntity->getEntityName(),
            $crossReferenceEntity->getEntityName(),
        ));
    }

    protected function buildCrossReferenceEntity(
        EntityInterface&EntityDescriptorInterface $fromEntity,
        EntityInterface&EntityDescriptorInterface $toEntity,
        string                                    $relationType = RelationInterface::REFERENCE,
        bool                                      $isRequired = true
    ): EntityDescriptorInterface&EntityInterface {
        // by default, we use the cross-reference relation like:
        // fromEntity.id -> crossReference.fromEntityId -> toEntity.id
        // Where fromEntity.id - leftKey,
        // crossReference.fromEntityId - crossReferenceKey,
        // toEntity.id - rightKey
        $crossReferenceEntity       = CrossReferenceEntity::instantiate(
            $this->namingStrategy->generateCrossReferenceEntityName($fromEntity, $toEntity),
            $fromEntity->getEntityName(),
            $toEntity->getEntityName(),
            $relationType,
            $isRequired
        );

        $crossReferenceEntity->resolveDependencies($this->container);
        $this->buildEntity($crossReferenceEntity);

        return $crossReferenceEntity;
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function buildInheritsEntity(EntityInterface & EntityDescriptorInterface $entity, string $inheritedEntity): void
    {
        $inheritedEntity            = $this->entityFactory->getEntity($inheritedEntity);

        $this->inheritProperties($entity, $inheritedEntity);
        $this->inheritRelations($entity, $inheritedEntity);
        $entity->describeReference($inheritedEntity, RelationInterface::INHERITANCE);
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function inheritProperties(EntityInterface & EntityDescriptorInterface $entity, EntityInterface $inheritedEntity, bool $readOnly = false): void
    {
        $inheritedEntityName        = $inheritedEntity->getEntityName();

        foreach ($inheritedEntity->getProperties() as $property) {

            // Inheritance Entity will override $inheritedEntity properties
            if ($entity->getProperty($property->getName(), false) !== null) {
                continue;
            }

            $property               = $property->inheritFrom($inheritedEntityName, $readOnly);

            // If property is typical, but it is already described in the entity, we should remove typical name
            if ($property->getTypicalName() !== null && $entity->getTypicalProperty($property->getTypicalName(), false) !== null) {
                $property->setTypicalName(null);
            }

            $entity->describeProperty($property);
        }
    }

    protected function inheritRelations(EntityInterface&EntityDescriptorInterface $entity, EntityInterface $inheritedEntity): void
    {
        foreach ($inheritedEntity->getRelations() as $relationName => $relation) {

            // Inheritance Entity will override $inheritedEntity relations
            if ($entity->findRelation($relationName) !== null) {
                continue;
            }

            $relation               = $this->inheritRelation($relation, $inheritedEntity, $entity);

            if ($relation !== null) {
                $entity->describeRelation($relation);
            }
        }
    }

    /**
     * @throws UnexpectedValue
     */
    protected function inheritRelation(RelationInterface $relation, EntityInterface $inheritedEntity, EntityInterface $entity): ?RelationInterface
    {
        if ($relation instanceof RelationInheritByInterface) {
            return $relation->relationInheritBy($inheritedEntity, $entity);
        }

        if ($relation instanceof DirectRelationInterface) {
            return $this->inheritDirectRelation($relation, $inheritedEntity, $entity);
        }

        if ($relation instanceof IndirectRelationInterface) {
            return $this->inheritIndirectRelation($relation, $inheritedEntity, $entity);
        }

        return null;
    }

    /**
     * @throws UnexpectedValue
     */
    protected function inheritDirectRelation(DirectRelationInterface $relation, EntityInterface $parentEntity, EntityInterface $childEntity): ?RelationInterface
    {
        if ($relation->getRightEntityName() === $childEntity->getEntityName()) {
            return null;
        }

        //
        // Special case:
        //
        // If parent entity has relation by primary key
        // we substitute it to left key from $childEntity
        // Example:
        // Magazine inherited Book
        // and
        // Book related to BookFormat over Book.id => BookFormat.book_id
        // then
        // it will be replacement to: Magazine.book_id => BookFormat.book_id
        if ($relation->getLeftKey()->isEquals($parentEntity->getPrimaryKey())) {
            $newRelation            = new DirectRelation(
                $childEntity->getEntityName(),
                clone $relation->getLeftKey(),
                $relation->getRightEntityName(),
                clone $relation->getRightKey(),
                $relation->getRelationType()
            );

            $newRelation->setIsRequired($relation->isRequired())
                ->setIsConsistent($relation->isConsistentRelations())
                ->setIsLeastOnce($relation->isLeastOnce());

            return $this->keepRole($relation, $newRelation);
        }

        //
        // In all other cases, we create nested relationships
        //
        $newRelation               = new IndirectRelation($childEntity->getEntityName(), $parentEntity->getEntityName(), $relation->getRightEntityName());
        $newRelation->setIsRequired($relation->isRequired())
            ->setIsConsistent($relation->isConsistentRelations())
            ->setIsLeastOnce($relation->isLeastOnce());

        return $this->keepRole($relation, $newRelation);
    }

    /**
     * @throws UnexpectedValue
     */
    protected function inheritIndirectRelation(IndirectRelationInterface $relation, EntityInterface $parentEntity, EntityInterface $childEntity): ?RelationInterface
    {
        if ($relation->getRightEntityName() === $childEntity->getEntityName()) {
            return null;
        }

        // Indirect Relation turns into another Indirect Relation

        $entityPath                 = $relation->getEntitiesPath();
        \array_unshift($entityPath, $childEntity->getEntityName());

        $newRelation               = new IndirectRelation(...$entityPath);
        $newRelation->setIsRequired($relation->isRequired())
            ->setIsConsistent($relation->isConsistentRelations())
            ->setIsLeastOnce($relation->isLeastOnce());

        return $this->keepRole($relation, $newRelation);
    }

    /**
     * An inherited relation keeps the parent's role, integrity and whether it is joined by role only: the child's
     * inherited reference property then finds its relation by role and adds no second one.
     */
    protected function keepRole(RelationInterface $inherited, RelationAbstract $relation): RelationAbstract
    {
        $relation->setRelationName($inherited->getRelationName())
            ->setIntegrity($inherited->getIntegrity(), $inherited->getDerivedFrom());

        return $inherited->isJoinedByRoleOnly() ? $relation->asJoinedByRoleOnly() : $relation;
    }

    #[\Override]
    public function dispose(): void {}
}
