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
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityStorageInterface;
use IfCastle\AQL\Entity\Property\ComplexProperty;
use IfCastle\AQL\Entity\Property\PropertyCrossReference;
use IfCastle\AQL\Entity\Relation\DirectRelation;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Entity\Relation\IndirectRelation;
use IfCastle\AQL\Entity\Relation\IndirectRelationInterface;
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

    #[\Override]
    public function buildReference(
        EntityDescriptorInterface&EntityInterface $fromEntity,
        (EntityDescriptorInterface&EntityInterface)|string $toEntity,
        string $relationType = RelationInterface::REFERENCE,
        bool $isRequired = true,
        ?string $propertyName = null
    ): void {
        $toEntity = $toEntity instanceof EntityInterface ? $toEntity : $this->entityFactory->getEntity($toEntity, true);

        ComplexProperty::describeReferenceByKey(
            $toEntity->getPrimaryKey(),
            $toEntity,
            $fromEntity,
            $relationType,
            $isRequired,
            $propertyName
        );
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
        $fromEntity->describeRelation($crossReferenceRelation->reverseRelation());

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

            return $newRelation;
        }

        //
        // In all other cases, we create nested relationships
        //
        $newRelation               = new IndirectRelation($childEntity->getEntityName(), $parentEntity->getEntityName(), $relation->getRightEntityName());
        $newRelation->setIsRequired($relation->isRequired())
            ->setIsConsistent($relation->isConsistentRelations())
            ->setIsLeastOnce($relation->isLeastOnce());

        return $newRelation;
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

        return $newRelation;
    }

    #[\Override]
    public function dispose(): void {}
}
