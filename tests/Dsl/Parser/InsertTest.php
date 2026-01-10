<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

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
}
