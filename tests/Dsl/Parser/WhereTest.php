<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Collate;
use IfCastle\Exceptions\UnexpectedValue;

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

    public function testWhereComparesUnderAnExplicitCollation(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE title COLLATE utf8mb4_nopad_bin = {t}'));
    }

    public function testWhereRejectsAQuotedCollationName(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected a collation name after COLLATE');

        $this->parseSql("WHERE title COLLATE 'utf8mb4_bin' = {t}");
    }

    public function testCollationNameOutsideLettersDigitsAndUnderscoresIsRefused(): void
    {
        $this->expectException(UnexpectedValue::class);

        new Collate(new Column('title'), 'utf8mb4_bin; DROP TABLE x');
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

    public function testWhereInListParameterWithoutParentheses(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE kind IN {kinds} AND id NOT IN {ids}',
            'WHERE kind IN ({kinds}) AND id NOT IN ({ids})'));
    }

    public function testWhereRowOfColumnsInRowsOfValues(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'WHERE (ownerId, Event.createdAt) IN ((1, {at}), ({owner}, "x")) AND (a, b) NOT IN ((2, 3))'
        ));
    }

    public function testWhereParenthesisedConditionWithACommaInAFunctionStaysAGroup(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE (COALESCE(a, 0) = 1 OR b = 2) AND c = 3'));
    }

    public function testWhereRowInsideAGroupOfConditions(): void
    {
        $this->executeCase(new ParserCaseDescriptor('WHERE ((a, b) IN ((1, 2)) OR c = 3) AND d = 4'));
    }

    public function testWhereRowRejectsARowOfValuesOfAnotherWidth(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A row of 2 columns got a row of 3 values');

        $this->parseSql('WHERE (a, b) IN ((1, 2), (1, 2, 3))');
    }

    public function testWhereRowRejectsAnythingButColumns(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A row before IN lists columns');

        $this->parseSql('WHERE (a, 1) IN ((1, 2))');
    }

    public function testWhereRowNeedsIn(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected IN or NOT IN after a row of columns');

        $this->parseSql('WHERE (a, b) = (1, 2)');
    }
}
