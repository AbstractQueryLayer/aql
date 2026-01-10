<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;

class ConditionsTest extends TestCase
{
    #[\Override]
    protected function parseSql(string $sql): NodeInterface
    {
        return (new Conditions())->parseTokens(AqlParser::createTokenIterator($sql));
    }

    public function testEqualProperties(): void
    {
        $this->executeCase(new ParserCaseDescriptor('property = property2'));
    }

    public function testComments(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = /** this is comment */ property2',
            'property = property2'
        ));
    }

    public function testDocComments(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property
        /**
         * doc comment
         */
         
        = property2',
            'property = property2'
        ));
    }

    public function testConstantQuoted(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = \'constant\'',
            'property = "constant"'
        ));
    }

    public function testConstantWithDoubleQuoted(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = "constant"',
            'property = "constant"'
        ));
    }

    public function testConstantBoolean(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property1 = TRUE OR property2 = FALSE'
        ));
    }

    public function testConstantInteger(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = 12345'
        ));
    }

    public function testConstantIntegerSign(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = -12345'
        ));
    }

    public function testConstantFloat(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = 0.2345'
        ));
    }

    public function testPropertyPrefix(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'entity1.property1 = entity2.property2'
        ));
    }

    public function testAnd(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property1 = property2 AND property1 = 5 AND property2 = "string"'
        ));
    }

    public function testOr(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property1 = property2 OR property1 = 5 OR property2 = "string"'
        ));
    }

    public function testAndOr(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property1 = property2 AND (property1 = 5 OR property2 = "string")'
        ));
    }

    public function testIsExpression(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property IS NULL OR property IS NOT NULL'
        ));
    }

    public function testIsBoolean(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property IS TRUE OR property IS NOT TRUE'
        ));
    }

    public function testLike(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property LIKE "string" OR property NOT LIKE "string"'
        ));
    }

    public function testFunction(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = function(1, "parameter2")'
        ));
    }

    public function testParameter(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property = {parameter}'
        ));
    }

    public function testBetween(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property BETWEEN 100 AND 500'
        ));
    }

    public function testNotBetween(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property NOT BETWEEN 100 AND 500'
        ));
    }

    public function testIn(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property IN (1, 2, "3")'
        ));
    }

    public function testNotIn(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property NOT IN (1, 2, "3")'
        ));
    }

    public function testIsNull(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property IS NULL'
        ));
    }

    public function testIsNotNull(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property IS NOT NULL'
        ));
    }

    public function testAndPlusOrProhibited(): void
    {
        $this->expectException(ParseException::class);

        $this->executeCase(new ParserCaseDescriptor(
            'property1 = property2 AND property1 = 5 OR property2 = "string"'
        ));
    }

    public function testAndPlusOrWithBrackets(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            'property1 = property2 AND (property1 = 5 OR property2 = "string")'
        ));
    }

    public function testAndPlusOrWithBrackets2(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            '(property1 = 1 OR property1 = 5) AND property2 = "string"'
        ));
    }

    public function testSubSubSubConditions(): void
    {
        $this->executeCase(new ParserCaseDescriptor(
            '((property1 = property2 AND (property1 = 5 OR property2 = "string")))'
        ));
    }
}
