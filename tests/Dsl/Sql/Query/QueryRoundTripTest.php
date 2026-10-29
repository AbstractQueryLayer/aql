<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\QueryOption;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnSerializeException;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A query written by toArray() and passed through JSON comes back from fromArray() as the same tree: the same class,
 * the same AQL and the same array when written again.
 */
final class QueryRoundTripTest extends TestCase
{
    /** @return iterable<string, array{string, class-string}> */
    public static function queries(): iterable
    {
        yield 'select' => [
            'SELECT id, name AS title FROM Test1 INNER JOIN Test2 ON (Test2.id = Test1.id)'
            . ' WHERE (first = {value} OR second = "x") AND id IN (1, 2) AND (a, b) IN ((1, 2), (3, {b}))'
            . ' GROUP BY id ORDER BY id DESC, name LIMIT 5, 10',
            Select::class,
        ];
        yield 'select distinct' => ['SELECT DISTINCT first FROM Test ORDER BY first', Select::class];
        yield 'select with having' => [
            'SELECT first, COUNT(*) AS n FROM Test GROUP BY first HAVING COUNT(*) > 1 OR first = "x" ORDER BY first',
            Select::class,
        ];
        yield 'count' => ['COUNT FROM Test WHERE id > {id} LIMIT 5', Count::class];
        yield 'select from a subquery' => [
            'SELECT * FROM (SELECT * FROM Test) as alias INNER JOIN Test2',
            Select::class,
        ];
        yield 'select with a list parameter' => [
            'SELECT id FROM Test WHERE id IN {ids} AND name LIKE "a%"',
            Select::class,
        ];
        yield 'union' => [
            '(SELECT id FROM Test WHERE tagId = 1 ORDER BY updatedAt DESC LIMIT 21)'
            . ' UNION (SELECT id FROM Test WHERE tagId = 2) ORDER BY updatedAt DESC LIMIT 21',
            Select::class,
        ];
        yield 'with' => [
            'WITH RECURSIVE cte1 AS (SELECT FROM ParentEntity), cte2 AS (SELECT FROM ChildEntity)'
            . ' SELECT name FROM cte1, ChildEntity',
            With::class,
        ];
        yield 'insert values' => [
            'INSERT INTO Entity (id, name) VALUES (1, "test"), (2, {name}) ON DUPLICATE KEY UPDATE name = "duplicate"',
            Insert::class,
        ];
        yield 'insert set' => ['INSERT INTO Entity SET name = "test", counter = 5', Insert::class];
        yield 'insert select' => ['INSERT INTO Entity (id, name) SELECT id, name FROM Other', Insert::class];
        yield 'replace' => ['REPLACE INTO Entity SET name = {name}', Insert::class];
        yield 'update' => [
            'UPDATE Entity SET name = "test", counter = counter + 1 WHERE id = {id} LIMIT 1',
            Update::class,
        ];
        yield 'update with conditions in a function' => [
            'UPDATE Entity SET bonus = IF(bonus IS NOT NULL AND attempts > 0, ISNULL(name), NULL) WHERE id = {id}',
            Update::class,
        ];
        yield 'update with a join' => [
            'UPDATE Entity INNER JOIN (SELECT * FROM Entity2) as test SET name = "test" WHERE id = 5',
            Update::class,
        ];
        yield 'delete' => ['DELETE FROM Entity WHERE id = {id} LIMIT 1', Delete::class];
        yield 'delete with a join' => [
            'DELETE EntityA, EntityB FROM EntityA INNER JOIN EntityB WHERE id = 5 LIMIT 0, 1',
            Delete::class,
        ];
    }

    /** @param class-string $class */
    #[DataProvider('queries')]
    public function testAParsedQueryRoundTrips(string $aql, string $class): void
    {
        $query                      = (new AqlParser())->parse($aql);
        $restored                   = $this->roundTrip($query);

        $this->assertInstanceOf($class, $restored);
        $this->assertSame($query->getAql(), $restored->getAql());
        $this->assertEquals(ArrayTyped::serialize($query), ArrayTyped::serialize($restored));
    }

    /** A plain Conditions given to setHaving() renders after GROUP BY with the HAVING keyword. */
    public function testPlainConditionsSetAsHavingRenderTheKeyword(): void
    {
        $query                      = (new AqlParser())->parse('SELECT id FROM Test GROUP BY id ORDER BY id');
        $query->setHaving((new Conditions(ConditionsInterface::TYPE_OR))->equal('id', 1)->equal('id', 2));

        $this->assertSame(
            'SELECT id FROM Test GROUP BY id HAVING (id = {?} OR id = {?}) ORDER BY id', $query->getAql()
        );
    }

    /** @return iterable<string, array{\Closure(): NodeInterface}> */
    public static function builtQueries(): iterable
    {
        yield 'count' => [static fn() => new Count('Test', (new Where())->equal('id', 1))];
        yield 'copy' => [static fn() => new Copy('Test', 'Archive', (new Where())->equal('id', 1))];
        yield 'unconditional update' => [static fn() => Update::entity('Test')->assign('name', 'x')->unconditional()];
    }

    /** @param \Closure(): NodeInterface $build */
    #[DataProvider('builtQueries')]
    public function testABuiltQueryRoundTrips(\Closure $build): void
    {
        $query                      = $build();
        $restored                   = $this->roundTrip($query);

        $this->assertInstanceOf($query::class, $restored);
        $this->assertSame($query->getQueryAction(), $restored->getQueryAction());
        $this->assertSame($query->getAql(), $restored->getAql());
        $this->assertEquals(ArrayTyped::serialize($query), ArrayTyped::serialize($restored));
    }

    public function testABuiltQueryKeepsItsLockAndAppliedValues(): void
    {
        $query                      = Select::from('Test')->forUpdate()->setLockWait(LockWait::SKIP_LOCKED)
                                    ->limit(3);
        $query->setWhere((new Where())->equal('id', new Parameter('id')));
        $query->applyParameters(['id' => 7]);

        $restored                   = $this->roundTrip($query);

        $this->assertSame(LockMode::FOR_UPDATE, $restored->getLockMode());
        $this->assertSame(LockWait::SKIP_LOCKED, $restored->getLockWait());
        $this->assertSame('Test', $restored->getMainEntityName());
        $this->assertSame($query->getAql(), $restored->getAql());
        $this->assertSame(7, $this->firstParameter($restored, 'id')->getConstantValue());
    }

    /** The links between parameters of one name are not written: the restored query builds them again. */
    public function testRepeatedParametersShareAValueAfterTheRoundTrip(): void
    {
        $restored                   = $this->roundTrip(
            (new AqlParser())->parse('SELECT id FROM Test WHERE (first = {value} OR second = {value})')
        );
        $restored->applyParameters(['value' => 'x']);

        $values                     = [];

        foreach ($this->parameters($restored) as $parameter) {
            $values[]               = $parameter->getConstantValue();
        }

        $this->assertSame(['x', 'x'], $values);
    }

    public function testRestoredChildrenPointToTheirParents(): void
    {
        $restored                   = $this->roundTrip((new AqlParser())->parse('SELECT id FROM Test WHERE id = 1'));

        $this->assertSame($restored, $restored->getWhere()->getParentNode());
        $this->assertSame($restored->getWhere(), $restored->getWhere()->getChildNodes()[0]->getParentNode());
    }

    /** @return iterable<string, array{callable}> */
    public static function setters(): iterable
    {
        yield 'closure' => [static fn() => null];
        yield 'function name' => ['strtolower'];
    }

    #[DataProvider('setters')]
    public function testAParameterSetterIsRefused(callable $setter): void
    {
        $query                      = Select::from('Test');
        $query->setWhere((new Where())->equal('id', (new Parameter('id'))->defineParameterSetter($setter)));

        $this->expectException(LogicalException::class);
        $this->expectExceptionMessage('setter');

        $query->toArray();
    }

    /** A setter named in the input would run on applyParameters(): it is not a property the input may set. */
    public function testASetterInTheInputIsRefused(): void
    {
        $array                      = ArrayTyped::serialize(new Parameter('id'));
        $array['setter']            = ['@array' => [self::class, 'fail']];

        $this->expectException(UnSerializeException::class);
        $this->expectExceptionMessage('setter');

        ArrayTyped::unserialize($array);
    }

    public function testAnUnknownPropertyIsRefused(): void
    {
        $this->expectException(UnSerializeException::class);

        Select::fromArray(['noSuchProperty' => 1]);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function craftedMembers(): iterable
    {
        yield 'a class that is not a node' => [[ArrayTyped::TYPE => \ArrayObject::class]];
        yield 'an abstract node' => [[ArrayTyped::TYPE => QueryAbstract::class]];
        yield 'an option without a value' => [[ArrayTyped::TYPE => QueryOption::class, 'n' => 'x']];
        yield 'an array that is not one' => [['@array' => 'x']];
    }

    /** @param array<mixed> $member */
    #[DataProvider('craftedMembers')]
    public function testAMemberThatIsNotANodeIsRefused(array $member): void
    {
        $array                      = ArrayTyped::serialize(Select::from('Test'));
        $array['childNodes']['@array']['WHERE'] = $member;

        $this->expectException(UnSerializeException::class);

        ArrayTyped::unserialize($array);
    }

    public function testAValueOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(UnSerializeException::class);
        $this->expectExceptionMessage('$unconditional');

        Select::fromArray(['unconditional' => 'yes']);
    }

    public function testARedefinedOptionIsWrittenWithItsNewValue(): void
    {
        $query                      = Select::from('Test');
        $query->getQueryOptions()->addOption(new QueryOption('timeout', 5));
        $query->getQueryOptions()->addOption(new QueryOption('timeout', 10));

        $restored                   = $this->roundTrip($query);

        $this->assertSame(10, $restored->getQueryOptions()->findOption('timeout')->getOptionValue());
    }

    /** The validator is asked about every class of the tree, not only the query's own. */
    public function testTheValidatorReachesNestedNodes(): void
    {
        $query                      = (new AqlParser())->parse('SELECT id FROM Test WHERE id = 1');
        $array                      = ArrayTyped::serialize($query);
        $validator                  = new class implements ArraySerializableValidatorInterface {
            public function isSerializationAllowed(?object $object = null): bool
            {
                return true;
            }

            public function isUnSerializationAllowed(string $type): bool
            {
                return $type !== Column::class;
            }
        };

        $this->expectException(UnSerializeException::class);
        $this->expectExceptionMessage(Column::class);

        ArrayTyped::unserialize($array, $validator);
    }

    private function roundTrip(NodeInterface $query): mixed
    {
        $json                       = \json_encode(ArrayTyped::serialize($query), JSON_THROW_ON_ERROR);

        return ArrayTyped::unserialize(\json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return list<Parameter> */
    private function parameters(NodeInterface $node): array
    {
        $parameters                 = [];

        foreach ($node->getChildNodes() as $child) {
            if ($child === null) {
                continue;
            }

            if ($child instanceof Parameter) {
                $parameters[]       = $child;
            }

            $parameters             = [...$parameters, ...$this->parameters($child)];
        }

        return $parameters;
    }

    private function firstParameter(NodeInterface $node, string $name): Parameter
    {
        foreach ($this->parameters($node) as $parameter) {
            if ($parameter->getParameterName() === $name) {
                return $parameter;
            }
        }

        $this->fail('No parameter ' . $name);
    }
}
