<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AOP\AspectsByGroupsInterface;
use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\AccessByGroupsMutableInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;

interface PropertyInterface extends AccessByGroupsMutableInterface, AspectsByGroupsInterface
{
    public const string T_BOOLEAN = 'boolean';

    public const string T_STRING = 'string';

    public const string T_ENUM = 'enum';

    public const string T_TEXT = 'text';

    public const string T_INT = 'int';

    public const string T_BIG_INT = 'bigint';

    public const string T_FLOAT = 'float';

    public const string T_UUID = 'uuid';

    public const string T_ULID = 'ulid';

    public const string T_DATE = 'date';

    public const string T_YEAR = 'year';

    public const string T_DATETIME = 'datetime';

    public const string T_TIME = 'time';

    public const string T_TIMESTAMP = 'timestamp';

    public const string T_JSON = 'json';

    public const string T_LIST = 'list';

    public const string T_OBJECT = 'object';

    /**
     * For virtual.
     */
    public const string T_TUPLE = 'tuple';

    /**
     * Means default properties group.
     */
    public const string GROUP_DEFAULT = '';

    /**
     * Properties created for internal algo and code.
     */
    public const string GROUP_INTERNAL = 'internal';

    /**
     * Means system properties groups.
     */
    public const string GROUP_SYSTEM = 'sys';

    public function getDefinition(?EntityFactoryInterface $entitiesFactory = null): DefinitionMutableInterface;

    public function getGroup(): string;

    public function inGroup(string $group): bool;

    public function setGroup(string $group): static;

    public function getType(): string;

    public function setType(string $type): static;

    public function getName(): string;

    public function setName(string $name): static;

    public function getTypicalName(): ?string;

    public function setTypicalName(string|null $typicalName): static;

    public function getFieldName(): string;

    public function setFieldName(string $fieldName): static;

    public function applyDbName(?string $dbName = null): static;

    public function getDefaultValue(): mixed;

    public function setDefaultValue(mixed $defaultValue): static;

    public function withDefaultValue(): static;

    public function getOnCreate(): ?FunctionReferenceInterface;

    public function setOnCreate(FunctionReferenceInterface $onCreate): static;

    public function getOnUpdate(): ?FunctionReferenceInterface;

    public function setOnUpdate(FunctionReferenceInterface $onUpdate): static;

    public function isNotNull(): bool;

    public function isNullable(): bool;

    public function setIsNotNull(bool $isNotNull): static;

    public function asNullable(): static;

    public function asNotNull(): static;

    public function isVirtual(): bool;

    /**
     * Return true if property needs post-processing.
     * For example, a property in the database is stored in JSON format and needs to be converted into a data structure.
     * Or the property is virtual and is computed based on the query results.
     *
     * Why this method is necessary: Properties that require post-processing cannot be used in SQL functions!
     */
    public function needsPostProcessing(): bool;

    public function setIsVirtual(bool $isVirtual): static;

    public function isSerializable(): bool;

    public function isUnsigned(): bool;

    public function asUnsigned(): static;

    public function isTuple(): bool;

    public function setIsTuple(bool $isTuple): static;

    public function getSize(): ?int;

    public function setSize(int $size): static;

    public function getMinimum(): float|int|null;

    public function setMinimum(float|int $minimum): static;

    public function getMaximum(): float|int|null;

    public function setMaximum(float|int $maximum): static;

    public function getMaxLength(): ?int;

    public function setMaxLength(int $maxLength): static;

    public function getMinLength(): ?int;

    public function setMinLength(int $minLength): static;

    public function getPattern(): ?string;

    public function setPattern(string $pattern): static;

    public function propertySerialize(mixed $value, ?ExecutionContextInterface $context = null): mixed;

    public function propertyUnSerialize(mixed $value, ?ExecutionContextInterface $context = null): mixed;

    public function getReferenceToEntity(): ?string;

    public function isReference(): bool;

    public function setReferenceToEntity(string $referenceToEntity): static;

    /**
     * Copy a property so that it becomes a reference to the original entity in the new entity.
     *
     * @return  $this
     */
    public function cloneAsReference(string $toEntity): static;

    public function isAbleResult(): bool;

    public function isAbleFilter(): bool;

    public function isAbleAssign(): bool;

    public function isAbleRelation(): bool;

    public function isAbleOrder(): bool;

    public function isAbleGroup(): bool;

    public function isAbleSearch(): bool;

    public function useWithResult(): static;

    public function useWithFilter(): static;

    public function useWithAssign(): static;

    public function useWithRelation(): static;

    public function useWithOrder(): static;

    public function useWithGroup(): static;

    public function useWithSearch(): static;

    public function getRelationType(): string;

    public function setRelationType(string $relationType): static;

    public function isPrimaryKey(): bool;

    public function setIsPrimaryKey(bool $isPrimaryKey): static;

    public function asPrimaryKey(): static;

    public function isAutoIncrement(): bool;

    public function setIsAutoIncrement(bool $isAutoIncrement): static;

    public function asAutoIncrement(): static;

    public function throwWrongUsing(PropertyContextInterface $context): void;

    public function getHandlerAfter(): ?callable;

    public function setHandlerAfter(callable $handlerAfter): static;

    public function handle(PropertyContextInterface $context): void;

    public function getEntityDefinitionHandler(): ?callable;

    public function setEntityDefinitionHandler(callable $entityDefinitionHandler): static;

    public function handleEntityDefinition(EntityInterface&EntityDescriptorInterface $ownerEntity, ContainerInterface $container): void;

    public function handleAfterRelationDefinition(EntityInterface $ownerEntity, ContainerInterface $container): void;

    public function getInheritedFrom(): ?string;

    public function inheritFrom(string $entityName, bool $readOnly = false): static;

    /**
     * Mark property as internal.
     * @return $this
     */
    public function asInternal(): static;

    /**
     * Mark property as allowed for public access.
     * So this property can be used in public AqlEndpoints.
     *
     * @return $this
     */
    public function asPublic(): static;

    public function _guessFormat(): ?\Closure;
}
