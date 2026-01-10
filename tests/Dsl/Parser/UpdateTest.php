<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

class UpdateTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Update())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testUpdateSimple(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET name = "test"'
        ));
    }

    public function testUpdateWithWhere(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET name = "test" WHERE id = 5'
        ));
    }

    public function testUpdateWithSeveralValuesAndLimit(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET name = "test", flag = TRUE, count = 5 WHERE id = 5 LIMIT 0, 5'
        ));
    }

    public function testUpdateWithDerived(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity INNER JOIN (SELECT * FROM Entity2) as test SET name = "test" WHERE id = 5'
        ));
    }
}
