<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

class DeleteTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Delete())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testDeleteSimple(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'DELETE FROM Entity'
        ));
    }

    public function testDeleteWhere(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'DELETE FROM Entity WHERE id = 5'
        ));
    }

    public function testDeleteWhereWithLimit(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'DELETE FROM Entity WHERE id = 5 LIMIT 0, 1'
        ));
    }

    public function testDeleteWithUsing(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'DELETE EntityA, EntityB FROM EntityA INNER JOIN EntityB WHERE id = 5 LIMIT 0, 1'
        ));
    }
}
