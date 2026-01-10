<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AOP\AspectsByGroupsTrait;
use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\AccessByGroupsTrait;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;

/**
 * Computed property from DerivedEntity.
 */
class DerivedProperty implements PropertyInterface
{
    use AccessByGroupsTrait {
        getAccessGroups as protected _getAccessGroups;
        hasAccess as protected _hasAccess;
    }

    use AspectsByGroupsTrait {
        getAspectGroups as protected _getAspectGroups;
        hasAspectGroup as protected _hasAspectGroup;
    }

    public function __construct(
        protected PropertyInterface $originalProperty,
        protected string $name = '',
    ) {
        if ($this->name === '') {
            $this->name             = $originalProperty->getName();
        }
    }


    #[\Override]
    public function getAccessGroups(): array
    {
        $accessGroups = $this->_getAccessGroups();

        if ($accessGroups === []) {
            return $this->originalProperty->getAccessGroups();
        }

        return $accessGroups;
    }

    #[\Override]
    public function hasAccess(string ...$accessGroups): bool
    {
        if ($this->_hasAccess(...$accessGroups)) {
            return true;
        }

        return $this->originalProperty->hasAccess(...$accessGroups);
    }

    #[\Override]
    public function setAccessGroups(string ...$accessGroups): static
    {
        foreach ($accessGroups as $group) {
            $this->accessGroups[]   = $group;
        }

        return $this;
    }

    #[\Override]
    public function getAspectGroups(): array
    {
        return \array_merge($this->originalProperty->getAspectGroups(), $this->_getAspectGroups());
    }

    #[\Override]
    public function hasAspectGroup(string ...$aspectGroups): bool
    {
        if ($this->_hasAspectGroup(...$aspectGroups)) {
            return true;
        }

        return $this->originalProperty->hasAspectGroup(...$aspectGroups);
    }

    #[\Override]
    public function addAspectGroup(string ...$aspectGroups): static
    {
        foreach ($aspectGroups as $group) {
            $this->aspectGroups[]   = $group;
        }

        return $this;
    }

    #[\Override]
    public function getDefinition(?EntityFactoryInterface $entitiesFactory = null): DefinitionMutableInterface
    {
        return $this->originalProperty->getDefinition($entitiesFactory);
    }

    #[\Override]
    public function getGroup(): string
    {
        return $this->originalProperty->getGroup();
    }

    #[\Override]
    public function inGroup(string $group): bool
    {
        return $this->originalProperty->inGroup($group);
    }

    #[\Override]
    public function setGroup(string $group): static
    {
        return $this;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->originalProperty->getType();
    }

    #[\Override]
    public function setType(string $type): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function setName(string $name): static
    {
        $this->name                 = $name;

        return $this;
    }

    #[\Override]
    public function getTypicalName(): ?string
    {
        return $this->originalProperty->getTypicalName();
    }

    #[\Override]
    public function setTypicalName(?string $typicalName): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getFieldName(): string
    {
        return $this->originalProperty->getFieldName();
    }

    #[\Override]
    public function setFieldName(string $fieldName): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function applyDbName(?string $dbName = null): static
    {
        return $this;
    }

    #[\Override]
    public function getDefaultValue(): mixed
    {
        return $this->originalProperty->getDefaultValue();
    }

    #[\Override]
    public function setDefaultValue(mixed $defaultValue): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        return $this;
    }

    #[\Override]
    public function getOnCreate(): ?FunctionReferenceInterface
    {
        return $this->originalProperty->getOnCreate();
    }

    #[\Override]
    public function setOnCreate(FunctionReferenceInterface $onCreate): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getOnUpdate(): ?FunctionReferenceInterface
    {
        return $this->originalProperty->getOnUpdate();
    }

    #[\Override]
    public function setOnUpdate(FunctionReferenceInterface $onUpdate): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isNotNull(): bool
    {
        return $this->originalProperty->isNotNull();
    }

    #[\Override]
    public function isNullable(): bool
    {
        return $this->originalProperty->isNullable();
    }

    #[\Override]
    public function setIsNotNull(bool $isNotNull): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function asNullable(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function asNotNull(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isVirtual(): bool
    {
        return true;
    }

    #[\Override]
    public function needsPostProcessing(): bool
    {
        return $this->originalProperty->needsPostProcessing();
    }

    #[\Override]
    public function setIsVirtual(bool $isVirtual): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isSerializable(): bool
    {
        return $this->originalProperty->isSerializable();
    }

    #[\Override]
    public function isUnsigned(): bool
    {
        return $this->originalProperty->isUnsigned();
    }

    #[\Override]
    public function asUnsigned(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isTuple(): bool
    {
        return $this->originalProperty->isTuple();
    }

    #[\Override]
    public function setIsTuple(bool $isTuple): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getSize(): ?int
    {
        return $this->originalProperty->getSize();
    }

    #[\Override] public function setSize(int $size): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getMinimum(): float|int|null
    {
        return $this->originalProperty->getMinimum();
    }

    #[\Override]
    public function setMinimum(float|int $minimum): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getMaximum(): float|int|null
    {
        return $this->originalProperty->getMaximum();
    }

    #[\Override]
    public function setMaximum(float|int $maximum): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getMaxLength(): ?int
    {
        return $this->originalProperty->getMaxLength();
    }

    #[\Override]
    public function setMaxLength(int $maxLength): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getMinLength(): ?int
    {
        return $this->originalProperty->getMinLength();
    }

    #[\Override]
    public function setMinLength(int $minLength): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getPattern(): ?string
    {
        return $this->originalProperty->getPattern();
    }

    #[\Override]
    public function setPattern(string $pattern): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function propertySerialize(mixed $value, ?ExecutionContextInterface $context = null): mixed
    {
        return $this->originalProperty->propertySerialize($value, $context);
    }

    #[\Override]
    public function propertyUnSerialize(mixed $value, ?ExecutionContextInterface $context = null): mixed
    {
        return $this->originalProperty->propertyUnSerialize($value, $context);
    }

    #[\Override]
    public function getReferenceToEntity(): ?string
    {
        return $this->originalProperty->getReferenceToEntity();
    }

    #[\Override]
    public function isReference(): bool
    {
        return $this->originalProperty->isReference();
    }

    #[\Override]
    public function setReferenceToEntity(string $referenceToEntity): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function cloneAsReference(string $toEntity): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isAbleResult(): bool
    {
        return $this->originalProperty->isAbleResult();
    }

    #[\Override]
    public function isAbleFilter(): bool
    {
        return $this->originalProperty->isAbleFilter();
    }

    #[\Override]
    public function isAbleAssign(): bool
    {
        return $this->originalProperty->isAbleAssign();
    }

    #[\Override]
    public function isAbleRelation(): bool
    {
        return $this->originalProperty->isAbleRelation();
    }

    #[\Override]
    public function isAbleOrder(): bool
    {
        return $this->originalProperty->isAbleOrder();
    }

    #[\Override]
    public function isAbleGroup(): bool
    {
        return $this->originalProperty->isAbleGroup();
    }

    #[\Override]
    public function isAbleSearch(): bool
    {
        return $this->originalProperty->isAbleSearch();
    }

    #[\Override]
    public function useWithResult(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithFilter(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithAssign(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithRelation(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithOrder(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithGroup(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function useWithSearch(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getRelationType(): string
    {
        return $this->originalProperty->getRelationType();
    }

    #[\Override]
    public function setRelationType(string $relationType): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isPrimaryKey(): bool
    {
        return $this->originalProperty->isPrimaryKey();
    }

    #[\Override]
    public function setIsPrimaryKey(bool $isPrimaryKey): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function asPrimaryKey(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function isAutoIncrement(): bool
    {
        return $this->originalProperty->isAutoIncrement();
    }

    #[\Override]
    public function setIsAutoIncrement(bool $isAutoIncrement): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function asAutoIncrement(): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function throwWrongUsing(PropertyContextInterface $context): void
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function getHandlerAfter(): ?callable
    {
        return $this->originalProperty->getHandlerAfter();
    }

    #[\Override]
    public function setHandlerAfter(callable $handlerAfter): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function handle(PropertyContextInterface $context): void
    {
        $this->originalProperty->handle($context);
    }

    #[\Override]
    public function getEntityDefinitionHandler(): ?callable
    {
        return $this->originalProperty->getEntityDefinitionHandler();
    }

    #[\Override]
    public function setEntityDefinitionHandler(callable $entityDefinitionHandler): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function handleEntityDefinition(
        EntityInterface&EntityDescriptorInterface $ownerEntity,
        ContainerInterface                        $container
    ): void {
        $this->originalProperty->handleEntityDefinition($ownerEntity, $container);
    }

    #[\Override]
    public function handleAfterRelationDefinition(
        EntityInterface $ownerEntity,
        ContainerInterface $container
    ): void {
        $this->originalProperty->handleAfterRelationDefinition($ownerEntity, $container);
    }

    #[\Override]
    public function getInheritedFrom(): ?string
    {
        return $this->originalProperty->getInheritedFrom();
    }

    #[\Override]
    public function inheritFrom(string $entityName, bool $readOnly = false): static
    {
        throw new \ErrorException('DerivedProperty is read-only');
    }

    #[\Override]
    public function asInternal(): static
    {
        return $this;
    }

    #[\Override]
    public function asPublic(): static
    {
        return $this;
    }

    #[\Override]
    public function _guessFormat(): ?\Closure
    {
        return $this->originalProperty->_guessFormat();
    }
}
