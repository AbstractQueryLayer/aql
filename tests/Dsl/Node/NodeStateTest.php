<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\Exceptions\UnSerializeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NodeState writes readonly properties from outside their classes: a protected one of the node and a private one of
 * its parent, each initialised once on create().
 */
final class NodeStateTest extends TestCase
{
    public function testReadonlyPropertiesOfTheClassAndItsParentAreRestored(): void
    {
        $node                       = ReadonlyStateNode::make('first', 3, ReadonlyStateMode::EXCLUSIVE);

        $restored                   = NodeState::create(ReadonlyStateNode::class, NodeState::read($node, null), null);

        $this->assertSame('first', $restored->getLabel());
        $this->assertSame(3, $restored->getCount());
        $this->assertSame(ReadonlyStateMode::EXCLUSIVE, $restored->getMode());
    }

    /** The node is created in strict mode: a numeric string is not coerced to int. */
    public function testAValueOfTheWrongTypeNamesThePropertyAndTheTypeError(): void
    {
        $this->expectException(UnSerializeException::class);
        $this->expectExceptionMessage(
            'Wrong value of ' . ReadonlyStateNode::class . '::$count: Cannot assign string to property '
            . ReadonlyStateNode::class . '::$count of type int'
        );

        NodeState::create(ReadonlyStateNode::class, ['count' => '3'], null);
    }

    /** @return iterable<string, array{string}> */
    public static function namesThatAreNotCases(): iterable
    {
        yield 'an unknown name'     => ['MISSING'];
        yield 'an alias of a case'  => ['ALIAS'];
        yield 'a constant'          => ['NOT_A_CASE'];
        yield 'the class constant'  => ['class'];
    }

    #[DataProvider('namesThatAreNotCases')]
    public function testOnlyACaseNameRestoresAnEnum(string $case): void
    {
        $this->expectException(UnSerializeException::class);
        $this->expectExceptionMessage('has no case ' . $case);

        NodeState::create(
            ReadonlyStateNode::class,
            ['mode' => ['@enum' => ReadonlyStateMode::class, 'case' => $case]],
            null
        );
    }
}
