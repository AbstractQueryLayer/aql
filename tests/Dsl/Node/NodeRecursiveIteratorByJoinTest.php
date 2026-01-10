<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use PHPUnit\Framework\TestCase;

class NodeRecursiveIteratorByJoinTest extends TestCase
{
    public function testJoins(): void
    {
        $query                      = (new AqlParser())->parse( /* @lang aql */
            'SELECT * FROM table1
            INNER JOIN table2 ON (table1.id = table2.id)
            LEFT JOIN table3 ON (table2.id = table3.id)
             {
                INNER JOIN table4 ON (table3.id = table4.id)
                {
                    INNER JOIN table5 ON (table4.id = table5.id)
                }
             }'
        );

        $iterator                   = new NodeRecursiveIteratorByJoin($query->getFrom());
        $result                     = [];

        foreach (new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST) as $join) {
            $this->assertInstanceOf(JoinInterface::class, $join);
            $result[]               = $join->getSubject()->getSubjectName();
        }

        $this->assertEquals([
            'Table1',
            'Table2',
            'Table3',
            'Table4',
            'Table5',
        ], $result);
    }
}
