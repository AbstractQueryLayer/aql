<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

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

}
