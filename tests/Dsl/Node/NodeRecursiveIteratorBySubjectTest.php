<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use PHPUnit\Framework\TestCase;

class NodeRecursiveIteratorBySubjectTest extends TestCase
{
    public function testJoins(): void
    {
        $query = (new AqlParser())->parse(
            /* @lang aql */
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

        $iterator = new NodeRecursiveIteratorBySubject($query->getFrom());
        $result   = [];

        foreach (new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST) as $subject) {
            $this->assertInstanceOf(SubjectInterface::class, $subject);
            $result[] = $subject->getSubjectName();
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
