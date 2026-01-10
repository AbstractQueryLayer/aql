<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIterator;

class WithTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new With())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testSimpleCte(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'WITH cte1 AS (SELECT FROM Simple) SELECT name, is_active FROM cte1',
            /** @lang aql */'WITH cte1 AS (SELECT FROM Simple) SELECT name, is_active FROM Cte1'
        ));
    }

    public function testCteOptionShouldBeEnabled(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The Query with "CTE" option is not allowed');

        (new With())->parseTokens(new TokensIterator(
            /** @lang aql */'WITH cte1 AS (SELECT FROM Simple) SELECT name FROM cte1'
        ));
    }

    public function testComplexCte(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */<<<AQL
                WITH RECURSIVE cte1 AS (SELECT FROM ParentEntity),
                cte2 AS (SELECT FROM ChildEntity),
                cte3 AS (SELECT FROM GrandChildEntity)
            
                SELECT name, is_active FROM cte1, ChildEntity
                AQL
            ,
            /** @lang aql */<<<AQL
                WITH RECURSIVE cte1 AS (SELECT FROM ParentEntity),
                cte2 AS (SELECT FROM ChildEntity),
                cte3 AS (SELECT FROM GrandChildEntity)
            
                SELECT name, is_active FROM Cte1 INNER JOIN ChildEntity
                AQL
        ));
    }
}
