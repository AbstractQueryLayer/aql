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

    /** @var array<class-string, \Closure(object, string, mixed): void> */
    private static array $assigners = [];

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

            $value                  = self::decode($value, $validator);
            $assign                 = self::assigner($properties[$name]->getDeclaringClass()->getName());

            try {
                $assign($node, $name, $value);
            } catch (\TypeError $error) {
                throw new UnSerializeException(
                    'Wrong value of ' . $class . '::$' . $name . ': ' . $error->getMessage(),
                    self::class
                );
            }
        }

        return $node;
    }

    /**
     * A function that assigns a property in the scope of the class that declares it.
     *
     * A readonly property can be initialised only from that scope. ReflectionProperty::setValue() would reach it too,
     * but it coerces the value as a non-strict call does ('3' becomes 3), where this assignment raises TypeError.
     *
     * @return \Closure(object, string, mixed): void
     */
    private static function assigner(string $declaringClass): \Closure
    {
        return self::$assigners[$declaringClass] ??= \Closure::bind(
            static function (object $node, string $name, mixed $value): void {
                $node->$name        = $value;
            },
            null,
            $declaringClass
        );
    }

    /** @return array<string, \ReflectionProperty> */
    private static function properties(string $class): array
    {
        if (isset(self::$properties[$class])) {
            return self::$properties[$class];
        }

        $properties                 = [];

        foreach (self::declaredProperties($class) as $property) {
            $name                   = $property->getName();

            // A redeclared protected or public property is one slot, already taken from the subclass; two private
            // properties of one name in a class and its parent are two slots that would share a key.
            if (!\array_key_exists($name, $properties)) {
                $properties[$name]  = $property;
            } elseif ($property->isPrivate() || $properties[$name]->isPrivate()) {
                throw new LogicalException(
                    'Property $' . $name . ' of ' . $class . ' is declared twice in its hierarchy'
                );
            }
        }

        return self::$properties[$class] = $properties;
    }

    /**
     * @return list<\ReflectionProperty> the serialized instance properties each class of the hierarchy declares
     *                                    itself, the given class first
     */
    private static function declaredProperties(string $class): array
    {
        $declared                   = [];

        for ($reflection = new \ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $className              = $reflection->getName();
            $declared               = [...$declared, ...\array_filter(
                $reflection->getProperties(),
                static fn(\ReflectionProperty $property): bool => !$property->isStatic()
                    && $property->getDeclaringClass()->getName() === $className
                    && $property->getAttributes(NotSerialized::class) === []
            )];
        }

        return $declared;
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

        $constant                   = $class . '::' . $case;

        // A case is a class constant; an enum may also declare constants that are not cases, or alias one.
        $enumCase                   = \defined($constant) ? \constant($constant) : null;

        if ($enumCase instanceof $class && $enumCase->name === $case) {
            return $enumCase;
        }

        throw new UnSerializeException('Enum ' . $class . ' has no case ' . $case, self::class);
    }
}
