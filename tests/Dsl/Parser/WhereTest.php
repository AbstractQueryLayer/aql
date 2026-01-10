<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

class WhereTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Where())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testWhereOrAnd(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE (property = 5 OR property3 = 6) AND property2 = 7'));
    }
}
