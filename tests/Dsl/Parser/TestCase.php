<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function executeCase(ParserCaseDescriptor $parserCaseDescriptor): void
    {
        $node                       = $this->parseSql($parserCaseDescriptor->getSql());

        $this->assertAqlStrings($parserCaseDescriptor->getAql(), $node->getAql(), 'Should be Matched');
    }

    abstract protected function parseSql(string $sql): NodeInterface;

    public function assertAqlStrings(string $expected, string $actual, string $comment = ''): void
    {
        $actual                     = \trim((string) \preg_replace('/\s+/', ' ', $actual));
        $expected                   = \trim((string) \preg_replace('/\s+/', ' ', $expected));

        $this->assertEquals($expected, $actual, $comment);
    }
}
