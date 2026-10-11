<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;

class InsertTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Insert())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testInsertWithSet(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity SET name = "test"'
        ));
    }

    public function testInsertWithSetList(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity SET name = "test", counter = 5'
        ));
    }

    public function testInsertWithSetListAndSubquery(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity SET name = "test", counter = (SELECT Entity2.counter FROM Entity2 WHERE Entity2.id = id)'
        ));
    }

    public function testInsertWithValues(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (id, name) VALUES (2, "my name")'
        ));
    }

    public function testInsertWithSeveralValues(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (id, name) VALUES (2, "my name"), (3, "your name")'
        ));
    }

    public function testInsertWithValuesPartial(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (id, name)'
        ));
    }

    public function testInsertWithSetAndDuplicateKey(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity SET name = "test", counter = 5 ON DUPLICATE KEY UPDATE name = "duplicate"'
        ));
    }

    public function testInsertWithValueListAndDuplicateKey(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (id, name) VALUES (1, "test") ON DUPLICATE KEY UPDATE name = "duplicate"'
        ));
    }

    public function testInsertValuesWithFunctionsParametersAndInterval(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (a, b, c)'
            . ' VALUES ({h}, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) + INTERVAL {s} SECOND)'
        ));
    }

    public function testInsertSeveralValuesRowsMixConstantsAndExpressions(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (a, b) VALUES (1, "x"), (2 + 3, NOW() - INTERVAL 1 DAY)'
        ));
    }

    public function testInsertValuesRejectColumnReference(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A column reference is not allowed in a VALUES expression');

        $this->parseSql(/** @lang aql */'INSERT INTO Entity (a, b) VALUES (1, name)');
    }

    public function testInsertDuplicateKeyWithConditionalExpression(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            /** @lang aql */'INSERT INTO Entity (id, count) VALUES (1, 1) ON DUPLICATE KEY UPDATE'
            . ' count = IF(start + INTERVAL {s} SECOND <= UTC_TIMESTAMP(6), 1, count + 1)'
        ));
    }
}
