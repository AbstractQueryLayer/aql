<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;

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

    public function testWhereWithIntervalOnTheLeft(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'WHERE windowStart + INTERVAL {s} SECOND <= UTC_TIMESTAMP(6)'
        ));
    }

    public function testWhereWithIntervalOnTheRight(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'WHERE expiresAt > UTC_TIMESTAMP(6) - INTERVAL 2 HOUR AND level - 1 >= 0'
        ));
    }

    public function testWhereRejectsAnUnknownIntervalUnit(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected INTERVAL unit');

        $this->parseSql('WHERE expiresAt > NOW() - INTERVAL 2 FORTNIGHT');
    }

    public function testWhereKeepsAConditionOfConstantZero(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE property = 5 AND 0'));
    }

    public function testWhereOfOnlyConstantZeroIsKept(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE 0'));
    }
}
