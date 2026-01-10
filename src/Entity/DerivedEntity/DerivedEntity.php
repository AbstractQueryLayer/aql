<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Functions\FunctionInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Entity\Relation\BuildingRequiredRelationInterface;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Entity\Relation\IndirectRelationInterface;
use IfCastle\AQL\Executor\Exceptions\PropertyNotFound;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\DisposableInterface;

class DerivedEntity implements DerivedEntityInterface, QueryExecutorResolverInterface, DisposableInterface
{
    /**
     * @var \WeakReference<EntityInterface>|null
     */
    protected \WeakReference|null $originalEntity = null;

    /**
     * @var \WeakReference<EntityFactoryInterface>|null
     */
    protected \WeakReference|null $entityFactory  = null;

    protected PropertyResolverInterface $propertyResolver;

    protected bool $shouldResolveProperties = true;

    /**
     * @var array<PropertyInterface>|null
     */
    protected array|null $properties = null;

    /**
     * @var array<RelationInterface>
     */
    protected array $relations = [];

    public function __construct(
        protected SubqueryInterface $subquery,
        protected SubjectInterface  $subject,
        EntityFactoryInterface $entityFactory,
        ?PropertyResolverInterface $propertyResolver = null
    ) {
        $this->entityFactory        = \WeakReference::create($entityFactory);

        if ($propertyResolver === null) {
            $propertyResolver       = new PropertyResolverIfExistsInSubquery($this->subquery, $this->getOriginalEntity(), $entityFactory);
        }

        $this->propertyResolver     = $propertyResolver;
    }

    #[\Override]
    public function getOriginalEntity(): EntityInterface
    {
        if ($this->originalEntity?->get() !== null) {
            return $this->originalEntity->get();
        }

        $originalEntity             = $this->entityFactory?->get()->getEntity($this->subquery->searchDerivedEntity());

        if ($originalEntity === null) {
            throw new \RuntimeException('Original entity not found');
        }

        $this->originalEntity       = \WeakReference::create($originalEntity);

        return $originalEntity;
    }

    #[\Override]
    public function setResolvePropertiesMode(bool $shouldResolveProperties): static
    {
        $this->shouldResolveProperties = $shouldResolveProperties;

        return $this;
    }

    #[\Override]
    public static function entity(): string
    {
        return '';
    }

    #[\Override]
    public function getEntityName(): string
    {
        return \ucfirst($this->subject->getSubjectAlias());
    }

    #[\Override]
    public function getTypicalEntityName(): string
    {
        return $this->getOriginalEntity()->getTypicalEntityName();
    }

    #[\Override]
    public function getSubject(): string
    {
        return $this->subject->getSubjectAlias();
    }

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->getOriginalEntity()->getStorageName();
    }

    #[\Override]
    public function getOptions(): ConfigInterface
    {
        return $this->getOriginalEntity()->getOptions();
    }

    #[\Override]
    public function getInheritedEntity(): ?string
    {
        return $this->getOriginalEntity()->getInheritedEntity();
    }

    #[\Override]
    public function getEntityAspects(): array
    {
        return $this->getOriginalEntity()->getEntityAspects();
    }

    #[\Override]
    public function findEntityAspect(string $aspectName): ?AspectDescriptorInterface
    {
        return $this->getOriginalEntity()->findEntityAspect($aspectName);
    }

    #[\Override]
    public function getProperties(): array
    {
        if (\is_array($this->properties)) {
            return $this->properties;
        }

        if ($this->shouldResolveProperties) {
            $this->properties       = $this->propertyResolver->resolveAllProperties();
            return $this->properties;
        }

        return [];

    }

    #[\Override]
    public function getTypicalProperties(): array
    {
        return $this->getOriginalEntity()->getTypicalProperties();
    }

    #[\Override]
    public function findAutoIncrement(): ?PropertyInterface
    {
        return $this->getOriginalEntity()->findAutoIncrement();
    }

    #[\Override]
    public function getKeys(): array
    {
        return $this->getOriginalEntity()->getKeys();
    }

    #[\Override]
    public function getFunctions(): array
    {
        return $this->getOriginalEntity()->getFunctions();
    }

    #[\Override]
    public function findFunction(FunctionReferenceInterface $functionReference): ?FunctionInterface
    {
        return $this->getOriginalEntity()->findFunction($functionReference);
    }

    #[\Override]
    public function getRelations(): array
    {
        return $this->getOriginalEntity()->getRelations();
    }

    #[\Override]
    public function getModifiers(): array
    {
        return $this->getOriginalEntity()->getModifiers();
    }

    #[\Override]
    public function getConstraints(): array
    {
        return $this->getOriginalEntity()->getConstraints();
    }

    #[\Override]
    public function getActions(): array
    {
        return $this->getOriginalEntity()->getActions();
    }

    #[\Override]
    public function getPrimaryKey(): ?KeyInterface
    {
        return $this->getOriginalEntity()->getPrimaryKey();
    }

    #[\Override]
    public function getTitleProperty(): string
    {
        return $this->getOriginalEntity()->getTitleProperty();
    }

    /**
     * @throws PropertyNotFound
     */
    #[\Override]
    public function getProperty(string $propertyName, bool $isThrow = true): ?PropertyInterface
    {
        if (\array_key_exists($propertyName, $this->properties ?? [])) {
            return $this->properties[$propertyName];
        }

        if (false === $this->shouldResolveProperties) {
            if ($isThrow) {
                throw new PropertyNotFound($this->getEntityName(), $propertyName);
            }

            return null;
        }

        $property                   = $this->propertyResolver->resolveProperty($propertyName);

        if ($property !== null) {
            $this->properties[$propertyName] = $property;
            return $property;
        }

        if ($isThrow) {
            throw new PropertyNotFound($this->getEntityName(), $propertyName);
        }

        return null;
    }

    #[\Override]
    public function getTypicalProperty(string $name, bool $isThrow = true): ?PropertyInterface
    {
        return $this->getOriginalEntity()->getTypicalProperty($name, $isThrow);
    }

    #[\Override]
    public function getDefaultColumns(): array
    {
        if ($this->subquery->getTuple()->whetherDefault()) {
            return $this->getOriginalEntity()->getDefaultColumns();
        }

        $columns                    = [];

        foreach ($this->subquery->getTuple()->getTupleColumns() as $tupleColumn) {
            $alias                  = $tupleColumn->getAliasOrColumnNameOrNull();

            if ($alias !== null) {
                $columns[]          = $alias;
            }
        }

        return $columns;
    }

    /**
     * @throws TransformationException
     */
    #[\Override]
    public function getRelation(string $toEntity): RelationInterface
    {
        if (\array_key_exists($toEntity, $this->relations)) {
            return $this->relations[$toEntity];
        }

        $relation                   = $this->findRelation($toEntity);

        if ($relation === null) {
            throw new TransformationException([
                'template'          => 'Derived-Entity {name} inherited from {entity} has no relations with {fromEntity}',
                'name'              => $this->getEntityName(),
                'entity'            => $this->originalEntity?->get()?->getEntityName() ?? '',
                'fromEntity'        => $toEntity,
            ]);
        }

        return $relation;
    }

    #[\Override]
    public function findRelation(string $toEntity): ?RelationInterface
    {
        if (\array_key_exists($toEntity, $this->relations)) {
            return $this->relations[$toEntity];
        }

        $toEntity                   = $this->entityFactory?->get()?->getEntity($toEntity);
        $relation                   = $this->originalEntity?->get()?->findRelation($toEntity?->getEntityName() ?? '');
        $originalName               = $this->originalEntity?->get()?->getEntityName();

        if ($relation !== null) {
            $this->relations[$toEntity->getEntityName()] = $this->transformRelation($relation);
            return $this->relations[$toEntity->getEntityName()];
        }

        $relation                  = $toEntity->findRelation($originalName ?? '');

        if ($relation === null) {
            return null;
        }

        $this->relations[$toEntity->getEntityName()] = $this->transformRelation($relation->reverseRelation());
        return $this->relations[$toEntity->getEntityName()];
    }

    #[\Override]
    public function resolveRelation(EntityInterface $toEntity): RelationInterface
    {
        return $this->getRelation($toEntity->getEntityName());
    }

    #[\Override]
    public function reverseRelation(?RelationInterface $relation = null): ?RelationInterface
    {
        return $relation?->reverseRelation();
    }

    #[\Override]
    public function isConsistentRelationWith(EntityInterface $withEntity): bool|null
    {
        if ($withEntity === $this) {
            return true;
        }

        if ($withEntity instanceof DerivedEntityInterface) {
            return $this->getEntityName() === $withEntity->getEntityName();
        }

        return $this->getOriginalEntity()->isConsistentRelationWith($withEntity);
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        return $this->getOriginalEntity()->resolveQueryExecutor($basicQuery, $entity);
    }

    #[\Override]
    public function getPostActions(): array
    {
        return $this->getOriginalEntity()->getPostActions();
    }

    #[\Override]
    public function dispose(): void
    {
        $this->originalEntity       = null;
        $this->entityFactory        = null;

        unset($this->subject, $this->subquery);
    }

    /**
     * The method performs two tasks:
     *
     * It replaces the left part in relations with the subject DerivedEntity.
     * The method adds the left key from the relations to the subquery.
     */
    protected function transformRelation(RelationInterface $originalRelation): RelationInterface
    {
        /**
         * To better understand what is happening here, let's imagine we have the following query:
         * ```sql
         * SELECT * FROM Main
         * JOIN (SELECT name FROM second) AS derived ON (derived.main_id = Main.id)
         * ```
         *
         * We have a relationship `$originalRelation` between the entities second and Main,
         * but we cannot simply include this relationship in the query
         * because we need to replace the left part with the name of the derived entity.
         */

        if ($originalRelation instanceof BuildingRequiredRelationInterface) {
            $originalRelation       = $originalRelation->buildRelations($this->entityFactory?->get());
        }

        $relation                   = $originalRelation->cloneWithSubjects(
            leftEntityName: $this->getEntityName()
        );

        $propertyResolver           = new AutoAddingPropertyResolver(
            $this->subquery, $this->getOriginalEntity(), $this->entityFactory->get()
        );

        /**
         * Now, to ensure the new relationship is correct,
         * we also need to add the left expression to the SELECT query.
         */
        if ($originalRelation instanceof DirectRelationInterface) {

            foreach ($originalRelation->getLeftKey()->getKeyColumns() as $column) {
                $propertyResolver->resolveProperty($column);
            }

        } elseif ($originalRelation instanceof IndirectRelationInterface) {

            $entitiesPath           = $originalRelation->getEntitiesPath();
            $previousEntity         = $this->entityFactory->get()->getEntity($entitiesPath[\count($entitiesPath) - 2]);
            $lastEntity             = $this->entityFactory->get()->getEntity($entitiesPath[\count($entitiesPath) - 1]);

            $lastRelation           = $previousEntity->resolveRelation($lastEntity);

            if (false === $lastRelation instanceof DirectRelationInterface) {
                throw new TransformationException([
                    'template'      => 'The relationship between entities {entityA} and {entityB} '
                                           . 'can only be of the DirectRelation type '
                                           . 'for a composite relationship like IndirectRelation. Got {relationType}.',
                    'relationType'  => $lastRelation::class,
                    'entityA'       => $previousEntity->getEntityName(),
                    'entityB'       => $lastEntity->getEntityName(),
                    'aql'           => $this->subquery->getAql(),
                ]);
            }

            foreach ($lastRelation->getLeftKey()->getKeyColumns() as $column) {
                $propertyResolver->resolveProperty($column, $lastRelation->getLeftEntityName());
            }

        } else {
            throw new TransformationException([
                'template'          => 'The strategy DerivedEntity '
                                       . 'is unable to correctly transform relationships of this type. {relationType}',
                'relationType'      => $relation::class,
                'aql'               => $this->subquery->getAql(),
            ]);
        }

        return $relation;
    }
}
