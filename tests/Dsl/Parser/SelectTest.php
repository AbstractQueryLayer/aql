<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Delete;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where as WhereNode;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class SelectTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Select())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testSimpleWithAll(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'SELECT * FROM Test',
            /** @lang aql */'SELECT * FROM Test'
        ));
    }

    public function testTupleWithAllMacros(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'SELECT * FROM Test',
            /** @lang aql */'SELECT * FROM Test'
        ));
    }

    public function testTuple(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'SELECT id, first_name, last_name FROM Test',
            /** @lang aql */ 'SELECT id, first_name, last_name FROM Test'
        ));
    }

    public function testTupleWithPrefixes(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, Test.first_name FROM Test',
            /** @lang aql */ 'SELECT id, Test.first_name FROM Test'
        ));
    }

    public function testFunctionKeepsAZeroArgument(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT COALESCE(id, 0) as a FROM Test',
            /** @lang aql */ 'SELECT COALESCE(id, 0) as "a" FROM Test'
        ));
    }

    public function testTupleWithAliases(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id as id1, Test.first_name as "first_name" FROM Test',
            /** @lang aql */ 'SELECT id as "id1", Test.first_name as "first_name" FROM Test'
        ));
    }

    public function testTupleWithDefaultColumns(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT @id FROM Test',
            /** @lang aql */ 'SELECT @id FROM Test'
        ));
    }

    public function testTupleFunction(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, function(1, "string") FROM Test',
            /** @lang aql */ 'SELECT id, function(1, "string") FROM Test'
        ));
    }

    public function testTupleNestedFunctions(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, function(function2(912), "string") FROM Test',
            /** @lang aql */ 'SELECT id, function(function2(912), "string") FROM Test'
        ));
    }

    public function testTupleVirtualFunction(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, @function(1, "string") FROM Test',
            /** @lang aql */ 'SELECT id, @function(1, "string") FROM Test'
        ));
    }

    public function testTupleSubquery(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, (SELECT name FROM Test2) FROM Test',
            /** @lang aql */ 'SELECT id, (SELECT name FROM Test2) FROM Test'
        ));
    }

    public function testTupleNested(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, [id, state, date FROM SubTest] FROM Test',
            /** @lang aql */ 'SELECT id, [id, state, date FROM SubTest] FROM Test'
        ));
    }

    public function testTupleNested2(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id, [id, state, date, [id, value FROM SubTest2] FROM SubTest] FROM Test',
            /** @lang aql */ 'SELECT id, [id, state, date, [id, value FROM SubTest2] FROM SubTest] FROM Test'
        ));
    }

    public function testWhere(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123'
        ));
    }

    public function testWhereAnd(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = "string" AND name = "string"',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = "string" AND name = "string"'
        ));
    }

    public function testWhereAndOr(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = "string" AND (name = "string1" OR name = "string2")',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = "string" AND (name = "string1" OR name = "string2")'
        ));
    }

    public function testGroupBy(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name'
        ));
    }

    public function testGroupByMulti(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name, first_name',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name, first_name'
        ));
    }

    public function testGroupByWithPrefix(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test GROUP BY Test.last_name, Test.first_name',
            /** @lang aql */ 'SELECT * FROM Test GROUP BY Test.last_name, Test.first_name'
        ));
    }

    public function testOrderBy(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 ORDER BY last_name',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 ORDER BY last_name'
        ));
    }

    public function testOrderByMulti(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 ORDER BY last_name, first_name DESC',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 ORDER BY last_name, first_name DESC'
        ));
    }

    public function testWhereGroupByOrderByLimit(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name ORDER BY name DESC LIMIT 10',
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 GROUP BY last_name ORDER BY name DESC LIMIT 0, 10'
        ));
    }

    public function testWhereOrderByLimitParameter(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE id = 123 ORDER BY name LIMIT {n}'
        ));
    }

    public function testJoin(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test1, Test2',
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2'
        ));
    }

    public function testInnerJoin(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test1 JOIN Test2 ON (Test2.id = Test1.id)',
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2 ON (Test2.id = Test1.id)'
        ));
    }

    public function testInnerJoin2(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2 ON (Test2.id = Test1.id)',
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2 ON (Test2.id = Test1.id)'
        ));
    }

    public function testNestedJoins(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2
            {
                LEFT JOIN Test3 {JOIN Test5}
            }',
            /** @lang aql */ 'SELECT * FROM Test1 INNER JOIN Test2 {LEFT JOIN Test3 {INNER JOIN Test5}}'
        ));
    }

    public function testDerivedFrom(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias',
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias'
        ));
    }

    public function testDerivedWithJoin(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias, Test2',
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias INNER JOIN Test2'
        ));
    }

    public function testDerivedWithInnerJoin(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias INNER JOIN Test2',
            /** @lang aql */ 'SELECT * FROM (SELECT * FROM Test) as alias INNER JOIN Test2'
        ));
    }

    public function testDerivedJoin(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test INNER JOIN (SELECT * FROM Test2) as table',
            /** @lang aql */ 'SELECT * FROM Test INNER JOIN (SELECT * FROM Test2) as table'
        ));
    }

    public function testIndexHints(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test t force index (a, b) JOIN t.role r USE INDEX (c) IGNORE INDEX (d)',
            /** @lang aql */ 'SELECT * FROM Test AS t FORCE INDEX (a, b) INNER JOIN t.role AS r USE INDEX (c) IGNORE INDEX (d)'
        ));
    }

    public function testParametersInJoinOnAndWhere(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM T t JOIN O o ON (o.id = {id} AND o.x = 1) WHERE t.id < {after} AND t.y = 2',
            /** @lang aql */ 'SELECT * FROM T AS t INNER JOIN O AS o ON (o.id = {id} AND o.x = 1) WHERE t.id < {after} AND t.y = 2'
        ));
    }

    public function testWhereEntity(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Test WHERE ENTITY Groups(is_active)',
            /** @lang aql */ 'SELECT * FROM Test WHERE ENTITY Groups(is_active)'
        ));
    }

    public function testUnion(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ <<<AQL
                    SELECT * FROM Test
                    UNION
                    SELECT * FROM Test2
                AQL,
            /** @lang aql */ <<<AQL
                    SELECT * FROM Test
                    UNION
                    SELECT * FROM Test2
                AQL
        ));
    }

    public function testUnionAllWithOrderByAndParenthesis(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ <<<AQL
                    SELECT * FROM Test ORDER BY id
                    UNION ALL
                    (SELECT * FROM Test2 ORDER BY id DESC)
                    ORDER BY id
                AQL,
            /** @lang aql */ <<<AQL
                    ( SELECT * FROM Test ORDER BY id )
                    UNION ALL
                    ( SELECT * FROM Test2 ORDER BY id DESC )
                    ORDER BY id
                AQL
        ));
    }

    public function testIntersectAndExcept(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ <<<AQL
                    SELECT * FROM Test GROUP BY id ORDER BY id LIMIT 10
                    INTERSECT
                    (
                        (SELECT * FROM Test2 ORDER BY id DESC)
                        UNION
                        (SELECT * FROM Test3 ORDER BY id LIMIT 10)
                        ORDER BY id
                    )
                    EXCEPT ALL
                    SELECT * FROM Test4 ORDER BY id LIMIT 10
                AQL,
            /** @lang aql */ <<<AQL
                    SELECT * FROM Test GROUP BY id ORDER BY id LIMIT 0, 10
                    INTERSECT
                    (
                        ( SELECT * FROM Test2 ORDER BY id DESC )
                        UNION
                        ( SELECT * FROM Test3 ORDER BY id LIMIT 0, 10 )
                        ORDER BY id
                    )
                    EXCEPT ALL
                    SELECT * FROM Test4 ORDER BY id LIMIT 0, 10
                AQL
        ));
    }

    public function testTupleWithArithmeticAndInterval(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT a - 1 as c, expiresAt + INTERVAL {s} DAY AS d FROM Test',
            /** @lang aql */ 'SELECT a - 1 as "c", expiresAt + INTERVAL {s} DAY as "d" FROM Test'
        ));
    }

    public function testForUpdateEndsTheSelectAfterLimit(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id FROM Test WHERE id = {id} ORDER BY id LIMIT 1 FOR UPDATE',
            /** @lang aql */ 'SELECT id FROM Test WHERE id = {id} ORDER BY id LIMIT 0, 1 FOR UPDATE'
        ));
    }

    public function testForUpdateWithoutWhere(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id FROM Test FOR UPDATE',
            /** @lang aql */ 'SELECT id FROM Test FOR UPDATE'
        ));
    }

    public function testLockInShareMode(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT id FROM Test WHERE id = 1 LOCK IN SHARE MODE',
            /** @lang aql */ 'SELECT id FROM Test WHERE id = 1 LOCK IN SHARE MODE'
        ));
    }

    public function testSkipLockedAndNowaitFollowTheLockClause(): void
    {
        foreach (['FOR UPDATE SKIP LOCKED', 'FOR UPDATE NOWAIT', 'LOCK IN SHARE MODE SKIP LOCKED',
            'LOCK IN SHARE MODE NOWAIT'] as $clause) {
            $this->executeCase(new ParserCaseDescriptor(
                'SELECT id FROM Test WHERE state = {s} LIMIT 1 ' . $clause,
                'SELECT id FROM Test WHERE state = {s} LIMIT 0, 1 ' . $clause
            ));
        }
    }

    public function testSkipWithoutLockedIsRejected(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql('SELECT id FROM Test FOR UPDATE SKIP');
    }

    public function testSkipLockedWithoutALockClauseIsRejected(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        // SKIP alone is a table alias, as in MariaDB, so the whole statement is what fails.
        (new AqlParser())->parse('SELECT id FROM Test SKIP LOCKED');
    }

    public function testIncompleteLockClauseIsRejected(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql('SELECT id FROM Test LOCK IN SHARE');
    }

    public function testForWithoutUpdateIsRejected(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql('SELECT id FROM Test FOR SHARE');
    }

    public function testParsedLockOnANodeThatIsNotASelectIsRejected(): void
    {
        $parser                     = new class extends Select {
            #[\Override]
            protected function newSelectNode(JoinInterface|string $from,
                TupleInterface|array|null                $tuple     = null,
                WhereNode|ConditionsInterface|array|null $where     = null,
                ?LimitInterface                           $limit     = null): QueryInterface
            {
                return new Delete($from);
            }
        };

        $this->expectException(Exceptions\ParseException::class);
        $this->expectExceptionMessage('FOR UPDATE');

        $parser->parseTokens(AqlParser::createTokenIterator('SELECT id FROM Test FOR UPDATE'));
    }

    public function testSelectWithoutFromRoundTrips(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT GET_LOCK({name}, 0) AS locked',
            /** @lang aql */ 'SELECT GET_LOCK({name}, 0) as "locked"'
        ));
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT IS_USED_LOCK({name}) AS holder, CONNECTION_ID() AS self',
            /** @lang aql */ 'SELECT IS_USED_LOCK({name}) as "holder", CONNECTION_ID() as "self"'
        ));
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT DATABASE()',
            /** @lang aql */ 'SELECT DATABASE()'
        ));
    }

    public function testSelectWithoutFromHasNoFromNode(): void
    {
        $select                     = $this->parseSql('SELECT 1 AS one');

        $this->assertInstanceOf(QueryInterface::class, $select);
        $this->assertNull($select->getFrom());
        $this->assertSame('', $select->getMainEntityName());
    }

    #[DataProvider('clausesOfSelectWithoutFrom')]
    public function testSelectWithoutFromRefusesEveryClause(string $aql): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql($aql);
    }

    public static function clausesOfSelectWithoutFrom(): iterable
    {
        yield 'WHERE'               => ['SELECT 1 WHERE 1 = 1'];
        yield 'JOIN'                => ['SELECT 1 JOIN Test'];
        yield 'GROUP BY'            => ['SELECT 1 GROUP BY a'];
        yield 'ORDER BY'            => ['SELECT 1 ORDER BY a'];
        yield 'LIMIT'               => ['SELECT 1 LIMIT 1'];
        yield 'FOR UPDATE'          => ['SELECT 1 FOR UPDATE'];
        yield 'LOCK IN SHARE MODE'  => ['SELECT 1 LOCK IN SHARE MODE'];
        yield 'star'                => ['SELECT *'];
        yield 'no expression'       => ['SELECT'];
    }

    public function testSubqueryWithoutFromIsRefused(): void
    {
        $this->expectException(Exceptions\ParseException::class);
        $this->expectExceptionMessage('FROM');

        $this->parseSql('SELECT id FROM Test WHERE id IN (SELECT 1)');
    }

    public function testColumnOfConstantZeroIsKept(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT a, 0 FROM Test',
            /** @lang aql */ 'SELECT a, 0 FROM Test'
        ));
    }

    public function testOnlyColumnOfConstantZeroIsKept(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT 0 FROM Test',
            /** @lang aql */ 'SELECT 0 FROM Test'
        ));
    }

    public function testUnionKeepsAColumnOfConstantZero(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ 'SELECT a FROM Test UNION SELECT 0 FROM Test',
            /** @lang aql */ 'SELECT a FROM Test UNION SELECT 0 FROM Test'
        ));
    }

    public function testUnclosedSubqueryAtEndOfInputThrowsParseException(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql('SELECT id FROM (SELECT id FROM Test');
    }
}
