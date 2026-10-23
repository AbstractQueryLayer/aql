<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnSerializeException;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

/**
 * Reads and writes the properties of a node as an array of plain values, so that every node class round-trips
 * through toArray()/fromArray() without a pair of methods of its own.
 *
 * The state is every instance property of the class and its parents, except those marked #[NotSerialized]. A
 * value is a scalar, null, an enum case, an array of such values or an ArraySerializableInterface object (a node),
 * written by ArrayTyped with its class. Anything else, such as a closure, cannot be written and raises
 * LogicalException naming the property.
 *
 * @internal
 */
final class NodeState
{
    /** Key of an enum case: its class; the case name is under ENUM_CASE. */
    private const string ENUM = '@enum';

    private const string ENUM_CASE = 'case';

    /** Key of a plain array, so that it is never read as a serialized object. */
    private const string ARRAY = '@array';

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private static array $properties = [];

    /**
     * @return array<string, mixed>
     * @throws LogicalException when a property holds a value that cannot be written
     */
    public static function read(NodeInterface $node, ?ArraySerializableValidatorInterface $validator): array
    {
        $state                      = [];

        foreach (self::properties($node::class) as $name => $property) {
            if (!$property->isInitialized($node)) {
                continue;
            }

            $path                   = $node::class . '::$' . $name;
            $state[$name]           = self::encode($property->getValue($node), $validator, $path);
        }

        return $state;
    }

    /**
     * @template T of NodeInterface
     *
     * @param class-string<T> $class
     * @param array<mixed> $state
     *
     * @return T
     *
     * @throws UnSerializeException when the state names a property the class does not serialize or holds a value
     *                              that cannot be read
     */
    public static function create(
        string $class,
        array $state,
        ?ArraySerializableValidatorInterface $validator
    ): NodeInterface {
        $properties                 = self::properties($class);
        $reflection                 = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new UnSerializeException('Class ' . $class . ' cannot be instantiated', self::class);
        }

        $node                       = $reflection->newInstanceWithoutConstructor();

        foreach ($state as $name => $value) {
            if (!\array_key_exists($name, $properties)) {
                throw new UnSerializeException('Unknown property ' . $class . '::$' . $name, self::class);
            }

            $property               = $properties[$name];
            $value                  = self::decode($value, $validator);

            // A readonly property can be initialised only from the scope of the class that declares it.
            $assign                 = \Closure::bind(
                static function (object $node) use ($name, $value): void {
                    $node->$name = $value;
                },
                null,
                $property->getDeclaringClass()->getName()
            );

            try {
                $assign($node);
            } catch (\TypeError $error) {
                throw new UnSerializeException(
                    'Wrong value of ' . $class . '::$' . $name . ': ' . $error->getMessage(),
                    self::class
                );
            }
        }

        return $node;
    }

    /** @return array<string, \ReflectionProperty> */
    private static function properties(string $class): array
    {
        if (isset(self::$properties[$class])) {
            return self::$properties[$class];
        }

        $properties                 = [];

        $reflection                 = new \ReflectionClass($class);

        for (; $reflection !== false; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic()
                    || $property->getDeclaringClass()->getName() !== $reflection->getName()
                    || $property->getAttributes(NotSerialized::class) !== []) {
                    continue;
                }

                // A redeclared protected or public property is one slot, already taken from the subclass; two private
                // properties of one name in a class and its parent are two slots that would share a key.
                if (\array_key_exists($property->getName(), $properties)) {
                    if (!$property->isPrivate() && !$properties[$property->getName()]->isPrivate()) {
                        continue;
                    }

                    throw new LogicalException(
                        'Property $' . $property->getName() . ' of ' . $class . ' is declared twice in its hierarchy'
                    );
                }

                $properties[$property->getName()] = $property;
            }
        }

        return self::$properties[$class] = $properties;
    }

    private static function encode(mixed $value, ?ArraySerializableValidatorInterface $validator, string $path): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \UnitEnum) {
            return [self::ENUM => $value::class, self::ENUM_CASE => $value->name];
        }

        if ($value instanceof ArraySerializableInterface) {
            return ArrayTyped::serialize($value, $validator);
        }

        if (\is_array($value)) {
            $result                 = [];

            foreach ($value as $key => $item) {
                $result[$key]       = self::encode($item, $validator, $path . '[' . $key . ']');
            }

            return [self::ARRAY => $result];
        }

        throw new LogicalException('Cannot serialize ' . \get_debug_type($value) . ' in ' . $path);
    }

    private static function decode(mixed $value, ?ArraySerializableValidatorInterface $validator): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (\array_key_exists(self::ARRAY, $value)) {
            if (!\is_array($value[self::ARRAY])) {
                throw new UnSerializeException('Expected an array under ' . self::ARRAY, self::class);
            }

            $result                 = [];

            foreach ($value[self::ARRAY] as $key => $item) {
                $result[$key]       = self::decode($item, $validator);
            }

            return $result;
        }

        if (\array_key_exists(self::ENUM, $value)) {
            return self::decodeEnum($value, $validator);
        }

        // Only a node is rebuilt here: ArrayTyped would call fromArray() of any class the input names.
        $class                      = $value[ArrayTyped::TYPE] ?? null;

        if (!\is_string($class) || !\is_subclass_of($class, NodeInterface::class)) {
            throw new UnSerializeException('Expected a node, got ' . \get_debug_type($class), self::class);
        }

        try {
            return ArrayTyped::unserialize($value, $validator);
        } catch (\TypeError $error) {
            // A node with a format of its own (QueryOption, LimitWithParameters) passes the values to its constructor.
            throw new UnSerializeException('Wrong value of ' . $class . ': ' . $error->getMessage(), self::class);
        }
    }

    /** @param array<mixed> $value */
    private static function decodeEnum(array $value, ?ArraySerializableValidatorInterface $validator): \UnitEnum
    {
        $class                      = $value[self::ENUM];
        $case                       = $value[self::ENUM_CASE] ?? null;

        if (!\is_string($class) || !\enum_exists($class) || !\is_string($case)) {
            throw new UnSerializeException('Expected an enum case', self::class);
        }

        if ($validator !== null && false === $validator->isUnSerializationAllowed($class)) {
            throw new UnSerializeException('Unserialize not allowed for type ' . $class, self::class);
        }

        foreach ($class::cases() as $enumCase) {
            if ($enumCase->name === $case) {
                return $enumCase;
            }
        }

        throw new UnSerializeException('Enum ' . $class . ' has no case ' . $case, self::class);
    }
}
