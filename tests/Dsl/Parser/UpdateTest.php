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

    public function testUpdateWithArithmeticAndInterval(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET attempts = attempts + 1,'
            . ' expiresAt = UTC_TIMESTAMP(6) + INTERVAL {s} MINUTE WHERE id = 5'
        ));
    }

    public function testUpdateWithConditionalExpression(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET attempts = IF(attempts >= 3, 0, attempts + 1) WHERE id = 5'
        ));
    }

    /** A function argument is a condition, as in SQL: IS NULL, IS NOT NULL, AND and OR, the groups bracketed. */
    public function testUpdateWithConditionsInsideAFunction(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET attempts = IF(bonus IS NULL, 0, attempts),'
            . ' bonus = IF(bonus IS NOT NULL AND attempts > 0, bonus, NULL),'
            . ' name = IF((bonus = 1 OR bonus = 2) AND name IS NULL, "x", name) WHERE id = 5'
        ));
    }

    public function testUpdateWithIsNullFunction(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'UPDATE Entity SET attempts = IF(ISNULL(bonus), 0, attempts + 1) WHERE id = 5'
        ));
    }

    public function testAndMixedWithOrInsideAFunctionIsRefused(): void
    {
        $this->expectException(Exceptions\ParseException::class);

        $this->parseSql('UPDATE Entity SET attempts = IF(bonus IS NULL AND attempts > 0 OR id = 1, 0, 1)');
    }
}
