<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AOP\AspectsByGroupsTrait;
use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\AccessByGroupsTrait;
use IfCastle\AQL\Dsl\Node\NodeHelper;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Manager\EntityDescriptorFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Relation\DirectRelation;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Exceptions\PropertyWrongUse;
use IfCastle\AQL\Executor\Helpers\ContextHelper;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;
use IfCastle\TypeDefinitions\TypeBool;
use IfCastle\TypeDefinitions\TypeDate;
use IfCastle\TypeDefinitions\TypeDateTime;
use IfCastle\TypeDefinitions\TypeFloat;
use IfCastle\TypeDefinitions\TypeInteger;
use IfCastle\TypeDefinitions\TypeJson;
use IfCastle\TypeDefinitions\TypeList;
use IfCastle\TypeDefinitions\TypeObject;
use IfCastle\TypeDefinitions\TypeOneOf;
use IfCastle\TypeDefinitions\TypeSelf;
use IfCastle\TypeDefinitions\TypeString;
use IfCastle\TypeDefinitions\TypeTime;
use IfCastle\TypeDefinitions\TypeTimestamp;
use IfCastle\TypeDefinitions\TypeUlid;
use IfCastle\TypeDefinitions\TypeUuid;
use IfCastle\TypeDefinitions\TypeYear;

class PropertyAbstract implements PropertyInterface
{
    use AccessByGroupsTrait;
    use AspectsByGroupsTrait;
    /**
     * Property group.
     */
    protected string $group         = '';

    /**
     * Size of property.
     */
    protected ?int $size            = null;

    protected bool $isUnsigned      = false;

    protected int|float|null $minimum = null;

    protected int|float|null $maximum = null;

    protected int|null $maxLength   = null;

    protected int|null $minLength   = null;

    protected string|null $pattern  = null;

    protected ?string $typicalName  = null;

    protected string $fieldName;

    protected mixed $defaultValue           = null;

    protected ?FunctionReferenceInterface $onCreate = null;

    protected ?FunctionReferenceInterface $onUpdate = null;

    /**
     * The Name of Entity from property was inherited.
     */
    protected ?string $inheritedFrom        = null;

    /**
     * If property is not null in DataBase.
     */
    protected bool $isNotNull       = true;

    /**
     * If property is virtual (not exists in DataBase).
     */
    protected bool $isVirtual       = false;

    /**
     * Whether the property is serializable?
     * Serializable properties are converted before being used
     * Example: JSON.
     */
    protected bool $isSerializable  = false;

    /**
     * If property is tuple (also isVirtual = true).
     */
    protected bool $isTuple         = false;

    /**
     * If property is primary key.
     */
    protected bool $isPrimaryKey    = false;

    protected bool $isAutoIncrement = false;

    /**
     * Property reference to another Entity.
     */
    protected ?string $referenceToEntity = null;

    /**
     * Type of relations.
     */
    protected ?string $relationType = null;

    /**
     * If property able result.
     */
    protected bool $ableResult      = true;

    /**
     * The property can be used in filtering.
     */
    protected bool $ableFilter      = true;

    /**
     * The property can be used in Assign:
     * property = value
     */
    protected bool $ableAssign      = true;

    protected bool $ableRelation    = true;

    protected bool $ableJoinConditions = true;

    /**
     * Property can be used in ORDER BY expression.
     */
    protected bool $ableOrder       = true;

    protected bool $ableGroup       = true;

    /**
     * Property can be used in Search expression.
     */
    protected bool $ableSearch      = true;

    /**
     * Handler called when entity building.
     *
     * @var callable
     */
    protected $entityDefinitionHandler;

    /**
     * @var callable
     */
    protected $handlerAfter;

    /**
     * @inheritDoc
     */
    public function __construct(protected string $name, protected string $type = self::T_STRING, bool $isNullable = false)
    {
        $this->fieldName            = $this->name;
        $this->isNotNull            = !$isNullable;

        $this->init();
    }

    public function __clone(): void
    {
        if ($this->onCreate !== null) {
            $this->onCreate         = clone $this->onCreate;
        }

        if ($this->onUpdate !== null) {
            $this->onUpdate         = clone $this->onUpdate;
        }

        $this->entityDefinitionHandler = null;
        $this->handlerAfter         = null;
    }

    /**
     * Additional method that participates in the initialization of the property.
     */
    protected function init(): void {}

    #[\Override]
    public function getGroup(): string
    {
        return $this->group;
    }

    #[\Override]
    public function inGroup(string $group): bool
    {
        return $this->group === $group;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setGroup(string $group): static
    {
        $this->group                = $group;
        return $this;
    }

    /**
     * @param string[] $accessGroups
     *
     * @return  $this
     */
    #[\Override]
    public function setAccessGroups(string ...$accessGroups): static
    {
        $this->accessGroups         = $accessGroups;
        return $this;
    }

    #[\Override]
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setSize(int $size): static
    {
        $this->size                 = $size;

        return $this;
    }

    #[\Override]
    public function getDefinition(?EntityFactoryInterface $entitiesFactory = null): DefinitionMutableInterface
    {
        $definition                 = match ($this->type) {
            self::T_BOOLEAN         => new TypeBool($this->name, false, !$this->isNotNull),
            self::T_STRING, self::T_TEXT => new TypeString($this->name, false, !$this->isNotNull),
            self::T_UUID            => new TypeUuid($this->name, false, !$this->isNotNull),
            self::T_ULID            => new TypeUlid($this->name, false, !$this->isNotNull),
            self::T_BIG_INT,
            self::T_INT             => new TypeInteger($this->name, false, !$this->isNotNull),
            self::T_FLOAT           => new TypeFloat($this->name, false, !$this->isNotNull),
            self::T_JSON            => new TypeJson($this->name, false, !$this->isNotNull),
            self::T_DATE            => new TypeDate($this->name, false, !$this->isNotNull),
            self::T_TIMESTAMP       => new TypeTimestamp($this->name, false, !$this->isNotNull),
            self::T_DATETIME        => new TypeDateTime($this->name, false, !$this->isNotNull),
            self::T_YEAR            => new TypeYear($this->name, false, !$this->isNotNull),
            self::T_TIME            => new TypeTime($this->name, false, !$this->isNotNull),
            self::T_ENUM            => new TypeOneOf($this->name, false, !$this->isNotNull),
            self::T_LIST            => new TypeList($this->name, new TypeSelf('self'), !$this->isNotNull),
            self::T_OBJECT          => new TypeObject($this->name, false, !$this->isNotNull),
            default                 => throw new EntityDescriptorException([
                'template'          => 'Property {property}.getDefinition impossible: unknown type {type}',
                'type'              => $this->type,
                'property'          => $this->name,
            ]),
        };

        if ($this->isUnsigned) {
            $definition->setMinimum(0);
        }

        if ($this->minimum !== null) {
            $definition->setMinimum($this->minimum);
        }

        if ($this->maximum !== null) {
            $definition->setMaximum($this->maximum);
        }

        if ($this->minLength !== null) {
            $definition->setMinLength($this->minLength);
        }

        if ($this->maxLength !== null) {
            $definition->setMaxLength($this->maxLength);
        }

        if ($this->pattern !== null) {
            $definition->setPattern($this->pattern);
        }

        return $definition;
    }

    #[\Override]
    public function getHandlerAfter(): ?callable
    {
        return $this->handlerAfter;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setHandlerAfter(callable $handlerAfter): static
    {
        $this->handlerAfter         = $handlerAfter;
        return $this;
    }

    /**
     *
     * @throws PropertyWrongUse
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function handle(PropertyContextInterface $context): void
    {
        /**
         * check the validity of using the property depending on the context.
         */

        $contextName                = ContextHelper::resolveContextName($context->getCurrentNode());

        $isAble                     = match ($contextName) {
            NodeContextInterface::CONTEXT_TUPLE     => $this->isAbleResult(),
            NodeContextInterface::CONTEXT_FILTER    => $this->isAbleFilter(),
            NodeContextInterface::CONTEXT_ASSIGN    => $this->isAbleAssign(),
            NodeContextInterface::CONTEXT_GROUP_BY  => $this->isAbleGroup(),
            NodeContextInterface::CONTEXT_ORDER_BY  => $this->isAbleOrder(),
            NodeContextInterface::CONTEXT_RELATIONS => $this->isAbleRelation(),
            NodeContextInterface::CONTEXT_JOIN_CONDITIONS => $this->isAbleJoinConditions(),

            default                                 => throw new PropertyWrongUse(
                $context->getCurrentEntity()->getEntityName(),
                $this->getName(),
                'Unknown usage context for expression: ' . NodeHelper::getNearestAql($context->getCurrentNode()),
            ),
        };

        if ($isAble === false) {
            $this->throwWrongUsing($context);
        }

        $context->getColumn()->setFieldName($this->getFieldName());

        $this->handleBefore($context, $contextName);

        if (\is_callable($this->handlerAfter)) {
            $handler                    = $this->handlerAfter;

            $handler($context, $this, $contextName);
        }
    }

    #[\Override]
    public function getEntityDefinitionHandler(): ?callable
    {
        return $this->entityDefinitionHandler;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setEntityDefinitionHandler(callable $entityDefinitionHandler): static
    {
        $this->entityDefinitionHandler   = $entityDefinitionHandler;
        return $this;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setType(string $type): static
    {
        $this->type                 = $type;
        return $this;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setName(string $name): static
    {
        $this->name                 = $name;
        return $this;
    }

    #[\Override]
    public function getTypicalName(): ?string
    {
        return $this->typicalName;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setTypicalName(string|null $typicalName): static
    {
        $this->typicalName          = $typicalName;
        return $this;
    }

    #[\Override]
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setFieldName(string $fieldName): static
    {
        $this->fieldName            = $fieldName;

        return $this;
    }

    #[\Override]
    public function applyDbName(?string $dbName = null): static
    {
        if ($dbName !== null) {
            $this->setFieldName($dbName);
        }

        return $this;
    }

    #[\Override]
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue         = $defaultValue;
        return $this;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        return $this;
    }

    #[\Override]
    public function getOnCreate(): ?FunctionReferenceInterface
    {
        return $this->onCreate;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setOnCreate(FunctionReferenceInterface $onCreate): static
    {
        $this->onCreate             = $onCreate;
        return $this;
    }

    #[\Override]
    public function getOnUpdate(): ?FunctionReferenceInterface
    {
        return $this->onUpdate;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setOnUpdate(FunctionReferenceInterface $onUpdate): static
    {
        $this->onUpdate             = $onUpdate;
        return $this;
    }

    #[\Override]
    public function isNotNull(): bool
    {
        return $this->isNotNull;
    }

    #[\Override]
    public function isNullable(): bool
    {
        return !$this->isNotNull;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setIsNotNull(bool $isNotNull): static
    {
        $this->isNotNull            = $isNotNull;
        return $this;
    }

    #[\Override]
    public function asNullable(): static
    {
        $this->isNotNull            = false;

        return $this;
    }

    #[\Override]
    public function asNotNull(): static
    {
        $this->isNotNull            = true;

        return $this;
    }

    #[\Override]
    public function isVirtual(): bool
    {
        return $this->isVirtual;
    }

    #[\Override]
    public function needsPostProcessing(): bool
    {
        return $this->isSerializable;
    }

    #[\Override]
    public function isSerializable(): bool
    {
        return $this->isSerializable;
    }

    #[\Override]
    public function isUnsigned(): bool
    {
        return $this->isUnsigned;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function asUnsigned(): static
    {
        $this->isUnsigned           = true;
        return $this;
    }

    #[\Override]
    public function getMinimum(): float|int|null
    {
        return $this->minimum;
    }

    /**
     *
     * @return $this
     */
    #[\Override]
    public function setMinimum(float|int $minimum): static
    {
        $this->minimum = $minimum;
        return $this;
    }

    #[\Override]
    public function getMaximum(): float|int|null
    {
        return $this->maximum;
    }

    /**
     *
     * @return $this
     */
    #[\Override]
    public function setMaximum(float|int $maximum): static
    {
        $this->maximum              = $maximum;
        return $this;
    }

    #[\Override]
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    #[\Override]
    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setMinLength(int $minLength): static
    {
        $this->minLength            = $minLength;
        return $this;
    }

    #[\Override]
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setPattern(string $pattern): static
    {
        $this->pattern              = $pattern;
        return $this;
    }

    #[\Override]
    public function propertySerialize($value, ?ExecutionContextInterface $context = null): mixed
    {
        return $value;
    }

    #[\Override]
    public function propertyUnSerialize($value, ?ExecutionContextInterface $context = null): mixed
    {
        return $value;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setIsVirtual(bool $isVirtual): static
    {
        $this->isVirtual            = $isVirtual;
        return $this;
    }

    #[\Override]
    public function isTuple(): bool
    {
        return $this->isTuple;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setIsTuple(bool $isTuple): static
    {
        $this->isTuple              = $isTuple;
        return $this;
    }

    #[\Override]
    public function getReferenceToEntity(): ?string
    {
        return $this->referenceToEntity;
    }

    /**
     * Returns TRUE if property referenced to another entity.
     */
    #[\Override]
    public function isReference(): bool
    {
        return $this->referenceToEntity !== null;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setReferenceToEntity(string $referenceToEntity): static
    {
        $this->referenceToEntity    = $referenceToEntity;
        return $this;
    }

    #[\Override]
    public function cloneAsReference(string $toEntity): static
    {
        $property                   = clone $this;

        $property->isPrimaryKey     = false;
        $property->isAutoIncrement  = false;
        $property->inheritedFrom    = null;
        $property->onCreate         = null;
        $property->onUpdate         = null;

        $property->setReferenceToEntity($toEntity);

        return $property;
    }

    #[\Override]
    public function isAbleResult(): bool
    {
        return $this->ableResult;
    }

    #[\Override]
    public function isAbleFilter(): bool
    {
        return $this->ableFilter;
    }

    #[\Override]
    public function isAbleAssign(): bool
    {
        return $this->ableAssign;
    }

    #[\Override]
    public function isAbleRelation(): bool
    {
        return $this->ableRelation;
    }

    public function isAbleJoinConditions(): bool
    {
        return $this->ableJoinConditions && false === $this->isVirtual();
    }

    #[\Override]
    public function isAbleOrder(): bool
    {
        return $this->ableOrder;
    }

    #[\Override]
    public function isAbleGroup(): bool
    {
        return $this->ableGroup;
    }

    #[\Override]
    public function isAbleSearch(): bool
    {
        return $this->ableSearch;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithResult(): static
    {
        $this->ableResult           = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithFilter(): static
    {
        $this->ableFilter           = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithAssign(): static
    {
        $this->ableAssign           = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithRelation(): static
    {
        $this->ableRelation         = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithOrder(): static
    {
        $this->ableOrder            = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithGroup(): static
    {
        $this->ableGroup            = true;
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function useWithSearch(): static
    {
        $this->ableSearch           = true;
        return $this;
    }

    #[\Override]
    public function getRelationType(): string
    {
        return $this->relationType ?? '';
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setRelationType(string $relationType): static
    {
        $this->relationType = $relationType;

        return $this;
    }

    #[\Override]
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setIsPrimaryKey(bool $isPrimaryKey): static
    {
        $this->isPrimaryKey         = $isPrimaryKey;

        return $this;
    }

    #[\Override]
    public function asPrimaryKey(): static
    {
        $this->isPrimaryKey         = true;

        return $this;
    }

    #[\Override]
    public function isAutoIncrement(): bool
    {
        return $this->isAutoIncrement;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setIsAutoIncrement(bool $isAutoIncrement): static
    {
        $this->isAutoIncrement      = $isAutoIncrement;
        return $this;
    }

    #[\Override]
    public function asAutoIncrement(): static
    {
        $this->isAutoIncrement      = true;
        return $this;
    }

    #[\Override]
    public function asInternal(): static
    {
        $this->setGroup(self::GROUP_INTERNAL);
        $this->setAccessGroups(self::ACCESS_INTERNAL);

        return $this;
    }

    #[\Override]
    public function asPublic(): static
    {
        $this->setAccessGroups(self::ACCESS_PUBLIC);

        return $this;
    }

    /**
     * This method is called from within the entity once
     * when the entity is being built.
     *
     * This method can modify entity structures to provide the desired functionality.
     */
    #[\Override]
    public function handleEntityDefinition(EntityInterface&EntityDescriptorInterface $ownerEntity, ContainerInterface $container): void
    {
        $this->onEntityDefinition($ownerEntity);
    }

    /**
     * The method is called after all the definitions are completed.
     */
    #[\Override]
    public function handleAfterRelationDefinition(EntityInterface $ownerEntity, ContainerInterface $container): void
    {
        // If property has referenceToEntity and relationType
        // and relations for that entity is not defined
        if (!empty($this->referenceToEntity) && $ownerEntity->findRelation($this->referenceToEntity) === null) {
            $this->addRelationToEntity($ownerEntity, $container->resolveDependency(EntityDescriptorFactoryInterface::class));
        }
    }

    /**
     * @throws PropertyWrongUse
     */
    #[\Override]
    public function throwWrongUsing(PropertyContextInterface $context): void
    {
        throw new PropertyWrongUse(
            $context->getCurrentEntity()->getEntityName(),
            $this->getName(), 'can not using in context ' . $context->getContextName(),
        );
    }

    #[\Override]
    public function getInheritedFrom(): ?string
    {
        return $this->inheritedFrom;
    }

    /**
     * Clone and inherit property from Entity.
     *
     *
     * @return  $this
     */
    #[\Override]
    public function inheritFrom(string $entityName, bool $readOnly = false): static
    {
        $property                   = clone $this;

        if ($property->inheritedFrom === null) {
            $property->inheritedFrom = $entityName;
        }

        if ($readOnly) {
            $this->ableAssign       = false;
        }

        return $property;
    }

    /**
     * Getting random value for unit tests.
     *
     * @see PropertyGuesser
     */
    #[\Override]
    public function _guessFormat(): ?\Closure
    {
        return null;
    }

    protected function onEntityDefinition(EntityInterface & EntityDescriptorInterface $entity): void
    {
        if (\is_callable($this->entityDefinitionHandler)) {
            $handler                = $this->entityDefinitionHandler;
            $handler($entity, $this);
        }
    }

    protected function addRelationToEntity(EntityInterface $ownerEntity, EntityDescriptorFactoryInterface $entityFactory): void
    {
        $entity                     = $entityFactory->getEntityDescriptor($this->referenceToEntity, true);

        $relation                   = new DirectRelation(
            $ownerEntity->getEntityName(), new Key($this->name),
            $entity->getEntityName(), $entity->getPrimaryKey(),
            $this->relationType,
        );

        //
        // If the property IS NOT NULL, then relation is required
        //
        $relation->setIsRequired($this->isNotNull());

        //
        // If entities are in different storages, then the relation is not consistent
        //
        if ($ownerEntity->getStorageName() !== $entity->getStorageName()) {
            $relation->setIsConsistent(false);
        }

        $ownerEntity->describeRelation($relation);
    }

    /**
     * @param string $contextName *
     *
     * @throws EntityDescriptorException
     */
    protected function handleBefore(PropertyContextInterface $context, string $contextName): void
    {
        if ($this->isSerializable) {
            $this->handleSerializableBefore($context);
        }

        if ($context->getColumn() !== null && $context->needDefinitionsForResult()) {
            $this->defineDefinitionForResult($context, $context->getColumn());
        }
    }

    protected function handleSerializableBefore(PropertyContextInterface $context): void
    {
        $currentNode                = $context->getCurrentNode();

        switch ($context->getContextName()) {
            case NodeContextInterface::CONTEXT_TUPLE:

                //
                // The processing of the Query result occurs through a handler (addAfterHandler)
                // that is called after the query is completed.
                //
                // to process the result, we need to find out the key in the results array
                //

                if ($currentNode instanceof TupleColumnInterface) {
                    $context->addUnSerializedColumn($currentNode, $this);
                }

                break;

            case NodeContextInterface::CONTEXT_FILTER:
            case NodeContextInterface::CONTEXT_ASSIGN:

                //
                // For filter and assignment expressions,
                // we serialize the right-side before the database query is executed.
                //
                if ($currentNode instanceof LROperationInterface) {

                    $constant       = $currentNode->getRightNode();

                    if ($constant instanceof ConstantInterface) {
                        $constant->setSubstitution(
                            new Variable($this->propertySerialize($constant->getConstantValue())),
                        );
                    }
                }
        }
    }

    /**
     * Determines the format of the result.
     *
     *
     * @throws EntityDescriptorException
     */
    protected function defineDefinitionForResult(PropertyContextInterface $context, ColumnInterface $column): void
    {
        $column->setDefinition($this->getDefinition($context));
    }
}
