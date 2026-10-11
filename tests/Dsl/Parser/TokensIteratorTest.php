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
}
