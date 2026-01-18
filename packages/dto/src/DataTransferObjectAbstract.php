<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Query\Count;
use IfCastle\AQL\Dsl\Sql\Query\Delete;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Equal;
use IfCastle\AQL\Dsl\Sql\Query\Insert;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Dsl\Sql\Query\Update;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\DTO\Exceptions\MappingException;
use IfCastle\AQL\DTO\Exceptions\RecordNotFound;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Exceptions\HydratorException;
use IfCastle\AQL\Executor\HydratorInterface;
use IfCastle\AQL\Executor\RecordInterface;
use IfCastle\Exceptions\LoggableException;
use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnSerializeException;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;
use IfCastle\TypeDefinitions\DefinitionStaticAwareInterface;
use IfCastle\TypeDefinitions\Exceptions\DecodingException;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\TypeBool;
use IfCastle\TypeDefinitions\TypeDateTime;
use IfCastle\TypeDefinitions\TypeFloat;
use IfCastle\TypeDefinitions\TypeInteger;
use IfCastle\TypeDefinitions\TypeJson;
use IfCastle\TypeDefinitions\TypeNull;
use IfCastle\TypeDefinitions\TypeObject;
use IfCastle\TypeDefinitions\TypeOneOf;
use IfCastle\TypeDefinitions\TypeString;
use IfCastle\TypeDefinitions\Value\ContainerSerializableInterface;

/**
 * Handle DTO without compilation of the code on runtime.
 */
abstract class DataTransferObjectAbstract implements DataTransferObjectInterface, ContainerSerializableInterface, RecordInterface
{
    /**
     * @throws \ReflectionException
     * @throws LoggableException
     */
    #[\Override]
    public static function getDtoMap(): DtoMap
    {
        $dtoClass                   = static::class;

        if (\array_key_exists($dtoClass, DtoMapHash::$map)) {
            return DtoMapHash::$map[$dtoClass];
        }

        $class                      = new \ReflectionClass($dtoClass);

        $attributes                 = $class->getAttributes(DtoMap::class);
        $mapEntityAttribute         = $attributes[0] ?? null;
        $mapEntity                  = $mapEntityAttribute?->newInstance();

        if ($mapEntity instanceof DtoMap === false) {
            throw new MappingException([
                'template'          => 'The DTO class {class} must have a #[MapEntity] attribute.',
                'class'             => $dtoClass,
            ]);
        }

        DtoMapHash::$map[$dtoClass] = new DtoMap($mapEntity->entityName, $mapEntity->scope, static::buildDtoProperties($class, $mapEntity));

        return DtoMapHash::$map[$dtoClass];
    }

    /**
     * @throws MappingException
     */
    public static function buildDtoProperties(\ReflectionClass $class, DtoMap $mapEntity, array $properties = []): array
    {
        // Read the properties of the DTO
        // Get properties with the #[Map] attribute
        foreach ($class->getProperties() as $property) {

            if (\array_key_exists($property->getName(), $properties)) {
                continue;
            }

            $attributes             = $property->getAttributes(Map::class);
            $mapAttribute           = $attributes[0] ?? null;
            $map                    = $mapAttribute?->newInstance();

            if ($map instanceof Map === false) {
                continue;
            }

            // Normalize the property name
            $propertyName           = $map->property === '' ? $property->getName() : $map->property;
            $hydratorClass          = null;

            // Try to check HydratorI interface
            $type                   = $property->getType();

            if (false === $type->isBuiltin() && $type->getName() !== \DateTimeImmutable::class) {
                $hydratorClass      = $type->getName();

                if ($hydratorClass instanceof HydratorInterface === false) {
                    throw new MappingException([
                        'template'      => 'The property {class}.{property} has complex type {type} without HydratorI interface.',
                        'property'      => $property->getName(),
                        'class'         => static::class,
                        'type'          => $hydratorClass,
                    ]);
                }
            }

            $definition             = null;

            if (false === $map->isHidden) {
                $definition         = $map->definition ?? static::propertyToDefinition($property, $map);
            }

            $properties[$property->getName()] = new Map(
                $propertyName,
                $map->isPrimaryKey,
                $map->isHidden,
                $map->isReadOnly,
                $map->entityName === '' ? $mapEntity->entityName : $map->entityName,
                $map->encodeKey,
                $hydratorClass,
                $definition
            );
        }

        // Search properties in parent DTOs
        $parentClass                = $class->getParentClass();

        if ($parentClass === false) {
            return $properties;
        }

        return static::buildDtoProperties($parentClass, $mapEntity, $properties);
    }

    /**
     * @throws MappingException
     */
    public static function propertyToDefinition(\ReflectionProperty $property, Map $map): DefinitionInterface
    {
        $type                       = $property->getType();

        if (false === $property->hasType()) {
            throw new MappingException([
                'template'          => 'The property {class}.{property} of the DTO must have a type.',
                'property'          => $property->getName(),
                'class'             => static::class,
            ]);
        }

        if ($type instanceof \ReflectionNamedType) {
            return self::namedTypeToDefinition($type, $property, $map);
        }

        if ($type instanceof \ReflectionUnionType) {
            throw new MappingException([
                'template'          => 'The property {class}.{property} of the DTO cannot be an union type.',
                'property'          => $property->getName(),
                'class'             => static::class,
            ]);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            throw new MappingException([
                'template'          => 'The property {class}.{property} of the DTO cannot be an intersection type.',
                'property'          => $property->getName(),
                'class'             => static::class,
            ]);
        }

        throw new MappingException([
            'template'          => 'The property {class}.{property} of the DTO cannot be handled.',
            'property'          => $property->getName(),
            'class'             => static::class,
        ]);
    }

    /**
     * @throws MappingException
     */
    public static function unionTypeToDefinition(\ReflectionUnionType $unionType, \ReflectionProperty $property, Map $map): DefinitionInterface
    {
        $types                      = new TypeOneOf($map->property, false === $unionType->allowsNull(), $unionType->allowsNull());

        foreach ($unionType->getTypes() as $type) {
            if ($type instanceof \ReflectionNamedType) {
                $types->describeCase(self::namedTypeToDefinition($type, $property, $map));
            } else {
                throw new MappingException([
                    'template'      => 'The property {class}.{property} of the DTO cannot be an intersection type.',
                    'property'      => $property->getName(),
                    'class'         => static::class,
                ]);
            }
        }

        return $types;
    }

    /**
     * @throws MappingException
     */
    public static function namedTypeToDefinition(\ReflectionNamedType $type, \ReflectionProperty $property, Map $map): DefinitionInterface
    {
        $propertyName               = $property->getName();

        if (false === $type->isBuiltin()) {

            $className              = $type->getName();

            // Support DateTimeImmutable value
            if ($className === \DateTimeImmutable::class) {
                return new TypeDateTime($propertyName, false === $type->allowsNull(), $type->allowsNull())
                    ->setEncodeKey($map->encodeKey)
                    ->asImmutable();
            }

            if ($className instanceof DefinitionStaticAwareInterface === false) {
                throw new MappingException([
                    'template'      => 'The property {class}.{property} has type {type} without DefinitionStaticAwareI interface.',
                    'property'      => $property->getName(),
                    'class'         => static::class,
                    'type'          => $className,
                ]);
            }

            return $className::definition()
                ->setName($propertyName)
                ->setEncodeKey($map->encodeKey)
                ->setIsRequired(false === $type->allowsNull())
                ->setIsNullable($type->allowsNull());
        }

        $isNullable                 = $type->allowsNull();

        return match ($type->getName()) {
            'null'                  => new TypeNull($propertyName, false === $isNullable)->setEncodeKey($map->encodeKey),
            'int'                   => new TypeInteger($propertyName, false === $isNullable, $isNullable)->setEncodeKey($map->encodeKey),
            'float'                 => new TypeFloat($propertyName, false === $isNullable, $isNullable)->setEncodeKey($map->encodeKey),
            'string'                => new TypeString($propertyName, false === $isNullable, $isNullable)->setEncodeKey($map->encodeKey),
            'bool'                  => new TypeBool($propertyName, false === $isNullable, $isNullable)->setEncodeKey($map->encodeKey),
            'array'                 => new TypeJson($propertyName, false === $isNullable, $isNullable)->setEncodeKey($map->encodeKey),

            default                 => throw new MappingException([
                'template'      => 'The property {class}.{property} has unknown type {type}.',
                'property'      => $property->getName(),
                'class'         => static::class,
                'type'          => $type->getName(),
            ])
        };
    }

    /**
     * @throws LoggableException
     * @throws \ReflectionException
     * @throws DecodingException
     */
    #[\Override]
    public static function instantiate(mixed $value, ?DefinitionInterface $definition = null): static
    {
        if (\is_array($value)) {
            return new static(...$value);
        }

        throw new DecodingException(static::definition(), 'Expected array', ['value' => \get_debug_type($value)]);
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     */
    #[\Override]
    public static function hydrate(array $data): static
    {
        $data                       = static::hydrateBefore($data);

        $dtoMap                     = static::getDtoMap();

        $parameters                 = [];

        foreach ($dtoMap->properties as $map) {

            $hydratorClass          = $map->hydratorClass;

            if (($data[$map->property] ?? null) === null && $map->definition?->isNullable()) {
                $parameters[]       = null;
                continue;
            }

            if ($hydratorClass instanceof HydratorInterface) {
                $parameters[]       = $hydratorClass::hydrate($data[$map->property] ?? []);
            } elseif ($map->definition instanceof TypeBool) {
                $parameters[]       = \array_key_exists($map->property, $data) ? (bool) $data[$map->property] : null;
            } elseif ($map->definition instanceof TypeFloat) {
                $parameters[]       = \array_key_exists($map->property, $data) ? (float) $data[$map->property] : null;
            } elseif ($map->definition instanceof TypeDateTime) {
                $parameters[]       = \array_key_exists($map->property, $data) ? $map->definition->decode($data[$map->property]) : null;
            } else {
                $parameters[]       = $data[$map->property] ?? null;
            }
        }

        return new static(...$parameters);
    }

    protected function hydrateSelf(array $data): static
    {
        $data                       = static::hydrateBefore($data);

        $dtoMap                     = static::getDtoMap();

        foreach ($dtoMap->properties as $property => $map) {

            $hydratorClass          = $map->hydratorClass;

            if(($data[$map->property] ?? null) === null && $map->definition?->isNullable()) {
                continue;
            }

            if ($hydratorClass instanceof HydratorInterface) {
                $this->$property    = $hydratorClass::hydrate($data[$map->property] ?? []);
            } elseif ($map->definition instanceof TypeBool) {
                $this->$property    = \array_key_exists($map->property, $data) ? (bool) $data[$map->property] : null;
            } elseif ($map->definition instanceof TypeFloat) {
                $this->$property    = \array_key_exists($map->property, $data) ? (float) $data[$map->property] : null;
            } elseif ($map->definition instanceof TypeDateTime) {
                $this->$property    = \array_key_exists($map->property, $data) ? $map->definition->decode($data[$map->property]) : null;
            } else {
                $this->$property    = $data[$map->property] ?? null;
            }
        }

        return $this;
    }

    protected static function hydrateBefore(array $data): array
    {
        return $data;
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     */
    #[\Override]
    public function extract(): array
    {
        $dtoMap                     = static::getDtoMap();

        $data                       = [];

        foreach ($dtoMap->properties as $property => $map) {

            $value                  = $this->{$property};

            $data[$map->property] = $value instanceof HydratorInterface ? $value->extract() : $value;
        }

        return $this->extractAfter($data);
    }

    protected function extractAfter(array $data): array
    {
        return $data;
    }

    /**
     * @throws LoggableException
     * @throws \ReflectionException
     */
    #[\Override]
    public function containerSerialize(): array|string|bool|int|float|null
    {
        return static::definition()->encode($this);
    }

    /**
     * @throws \ReflectionException
     * @throws \JsonException
     * @throws LoggableException
     */
    #[\Override]
    public function containerToString(): string
    {
        $value                      = $this->containerSerialize();

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return \json_encode($value, JSON_THROW_ON_ERROR);
    }

    #[\Override]
    public static function count(AqlExecutorInterface $aqlExecutor, array $filters = []): int
    {
        return $aqlExecutor->executeAql(static::selectCount($filters))->getFirstColumnAsInt();
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     * @throws RecordNotFound
     */
    #[\Override]
    public static function fetchOne(AqlExecutorInterface $aqlExecutor, array $filters = []): static
    {
        return static::findOne($aqlExecutor, $filters) ?? throw new RecordNotFound(static::getDtoMap()->entityName);
    }

    #[\Override]
    public static function findOne(AqlExecutorInterface $aqlExecutor, array $filters = []): ?static
    {
        $results                    = static::fetch($aqlExecutor, $filters, [], 1);

        return $results !== [] ? $results[0] : null;
    }

    /**
     * @throws RecordNotFound
     */
    #[\Override]
    public static function fetchById(AqlExecutorInterface $aqlExecutor, float|int|string $id): static
    {
        return static::findOne($aqlExecutor, [EntityFactoryInterface::TYPICAL_PREFIX . 'id' => $id])
            ?? throw new RecordNotFound(static::getDtoMap()->entityName, $id);
    }

    #[\Override]
    public static function findById(AqlExecutorInterface $aqlExecutor, float|int|string $id): ?static
    {
        return static::findOne($aqlExecutor, [EntityFactoryInterface::TYPICAL_PREFIX . 'id' => $id]);
    }

    #[\Override]
    public static function fetch(AqlExecutorInterface $aqlExecutor, array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $result                     = [];

        foreach ($aqlExecutor->executeAql(static::select($filters, $orderBy, $limit, $offset))->toArray() as $row) {
            $result[]               = static::hydrate($row);
        }

        return $result;
    }

    #[\Override]
    public static function selectCount(array $filters = []): Select
    {
        $dtoMap                     = static::getDtoMap();

        $query                      = new Count(
            $dtoMap->entityName,
            Conditions::keyValueToExpressions(static::dtoQueryFiltersGenerator($filters)),
            new Limit($limit ?? 0, $offset ?? 0)
        );

        static::dtoQueryGeneratorAfter($query);

        return $query;
    }

    #[\Override]
    public static function select(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): Select
    {
        $dtoMap                     = static::getDtoMap();

        $query                      = new Select(
            $dtoMap->entityName,
            static::dtoQueryColumnsGenerator(),
            Conditions::keyValueToExpressions(static::dtoQueryFiltersGenerator($filters)),
            new Limit($limit ?? 0, $offset ?? 0)
        );

        static::dtoQueryGeneratorAfter($query);

        return $query;
    }

    /**
     *
     * @return $this
     *
     * @throws HydratorException
     * @throws LoggableException
     * @throws \ReflectionException
     */
    #[\Override]
    public function insert(AqlExecutorInterface $aqlExecutor): static
    {
        $dtoMap                     = static::getDtoMap();

        $query                      = new Insert($dtoMap->entityName, $this->dtoQueryAssignsGenerator());

        static::dtoQueryGeneratorAfter($query);

        $lastRow                    = $aqlExecutor->executeAql($query)->getLastRow();

        return $this->hydrateSelf($lastRow ?? []);
    }

    #[\Override]
    public function update(AqlExecutorInterface $aqlExecutor): static
    {
        $dtoMap                     = static::getDtoMap();

        $query                      = new Update($dtoMap->entityName, $this->dtoQueryFiltersForUpdateDelete(), new Limit(1));
        $query->assignKeyValues($this->dtoQueryAssignsGenerator(true));

        static::dtoQueryGeneratorAfter($query);

        $aqlExecutor->executeAql($query);

        return $this;
    }

    #[\Override]
    public function delete(AqlExecutorInterface $aqlExecutor): static
    {
        $dtoMap                     = static::getDtoMap();

        $query                      = new Delete($dtoMap->entityName, $this->dtoQueryFiltersForUpdateDelete(), new Limit(1));

        static::dtoQueryGeneratorAfter($query);

        $aqlExecutor->executeAql($query);

        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     * @throws LogicalException
     */
    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        $dtoMap                     = static::getDtoMap();

        $array                      = [];

        foreach ($dtoMap->properties as $property => $map) {

            $value                  = $this->{$property};

            if (\is_object($value) && $validator !== null && false === $validator->isSerializationAllowed($value)) {
                throw new LogicalException('Serialize not allowed for type ' . $value::class);
            }

            if ($value instanceof ArraySerializableInterface) {
                $value              = $value->toArray($validator);
            } elseif ($map->definition !== null) {
                $value              = $map->definition->encode($value);
            }

            $array[$property]       = $value;
        }

        return $array;
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     * @throws UnSerializeException
     */
    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        $dtoMap                     = static::getDtoMap();

        $parameters                 = [];

        foreach ($dtoMap->properties as $property => $map) {

            $class                 = $map->hydratorClass;

            if ($class !== null && $validator !== null && false === $validator->isUnSerializationAllowed($class)) {
                throw new UnSerializeException('Unserialize not allowed for type ' . $class, static::class);
            }

            $value                  = $array[$property] ?? null;

            if ($class instanceof ArraySerializableInterface) {
                $parameters[]       = $class::fromArray($value, $validator);
            } elseif ($map->definition !== null) {
                $parameters[]       = $map->definition->decode($value);
            }
        }

        return new static(...$parameters);
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     */
    #[\Override]
    public static function definition(): DefinitionMutableInterface
    {
        $dtoMap                     = static::getDtoMap();

        $definition                 = new TypeObject($dtoMap->entityName);
        $definition->setInstantiableClass(static::class);

        foreach ($dtoMap->properties as $map) {
            if ($map->isHidden === false) {
                $definition->describe($map->definition);
            }
        }

        return $definition;
    }

    protected static function dtoQueryColumnsGenerator(): array
    {
        $dtoMap                     = static::getDtoMap();
        $columns                    = [];

        foreach ($dtoMap->properties as $map) {
            $columns[]              = new TupleColumn(new Column($map->property, $map->entityName), $map->property);
        }

        return $columns;
    }

    protected static function dtoQueryFiltersGenerator(array $filters): array
    {
        return $filters;
    }

    /**
     * @throws \ReflectionException
     * @throws LoggableException
     * @throws HydratorException
     */
    protected function dtoQueryAssignsGenerator(bool $forUpdate = false): array
    {
        $dtoMap                     = static::getDtoMap();
        $columns                    = [];

        foreach ($dtoMap->properties as $property => $map) {

            if ($map->isPrimaryKey && empty($this->$property)) {
                continue;
            }

            if ($map->isReadOnly) {
                continue;
            }

            if ($forUpdate && $map->isPrimaryKey) {
                continue;
            }

            $value                  = $this->{$property};

            if ($value instanceof HydratorInterface) {
                $value              = $value->extract();
            } elseif ($value instanceof ContainerSerializableInterface) {
                $value              = $value->containerSerialize();
            } elseif (!\is_scalar($value) && $value !== null && \is_array($value)) {
                throw new HydratorException([
                    'template'      => 'The property {class}.{property} must be scalar, null or HydratorI, SerializableI object. Got {type}.',
                    'property'      => $property,
                    'class'         => static::class,
                    'type'          => \get_debug_type($value),
                ]);
            }

            $columns[]              = new Assign(new Column($map->property, $map->entityName), new Variable($value));
        }

        return $columns;
    }

    /**
     * @throws \ReflectionException
     * @throws MappingException
     * @throws LoggableException
     */
    protected function dtoQueryFiltersForUpdateDelete(): array
    {
        $dtoMap                     = static::getDtoMap();

        foreach ($dtoMap->properties as $property => $map) {
            if ($map->isPrimaryKey) {
                return [new Equal(new Column($map->property, $map->entityName), new Variable($this->$property))];
            }
        }

        throw new MappingException([
            'template'              => 'The DTO {class} has no primary key.',
            'class'                 => static::class,
        ]);
    }

    protected static function dtoQueryGeneratorAfter(QueryInterface $query): void {}
}
