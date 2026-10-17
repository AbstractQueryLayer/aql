<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

/** A top-level query may open with a parenthesised SELECT: one ordered, limited branch per member of a union. */
class AqlParserUnionTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new AqlParser())->parse($sql);
    }

    public function testParenthesisedMembersWithTheirOwnOrderAndLimit(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */ <<<AQL
                    (SELECT id FROM Test WHERE tagId = 1 ORDER BY updatedAt DESC LIMIT 21)
                    UNION
                    (SELECT id FROM Test WHERE tagId = 2 ORDER BY updatedAt DESC LIMIT 21)
                    ORDER BY updatedAt DESC LIMIT 21
                AQL,
            /** @lang aql */ <<<AQL
                    ( SELECT id FROM Test WHERE tagId = 1 ORDER BY updatedAt DESC LIMIT 0, 21 )
                    UNION
                    ( SELECT id FROM Test WHERE tagId = 2 ORDER BY updatedAt DESC LIMIT 0, 21 )
                    ORDER BY updatedAt DESC LIMIT 0, 21
                AQL
        ));
    }

    public function testParenthesisedSingleSelect(): void
    {
        $this->executeCase(new ParserCaseDescriptor('(SELECT id FROM Test)', 'SELECT id FROM Test'));
    }
}
