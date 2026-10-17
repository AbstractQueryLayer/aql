<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use PHPUnit\Framework\TestCase;

class TokensIteratorTest extends TestCase
{
    public function testCurrentLineAtEndOfInputIsAnInteger(): void
    {
        $tokens                     = new TokensIterator('');

        $this->assertSame(0, $tokens->getCurrentLine());
    }

    public function testPhpKeywordsArePlainWords(): void
    {
        $aql                        = 'SELECT list, class, static, fn, print, use, var, readonly, case, for, clone,'
                                    . ' trait, match FROM Trait AS use WHERE use.class = 1 AND (list = 2 OR fn = 3)'
                                    . ' AND MATCH(title, {q}) ORDER BY static LIMIT 0, 5 FOR UPDATE';

        $this->assertSame($aql, (new AqlParser())->parse($aql)->getAql());
    }

    public function testLowercaseLogicalOperatorsStayOperators(): void
    {
        $query                      = (new AqlParser())->parse('SELECT id FROM Book WHERE a = 1 and (b = 2 or c = 3)');

        $this->assertSame('SELECT id FROM Book WHERE a = 1 AND (b = 2 OR c = 3)', $query->getAql());
    }
}
