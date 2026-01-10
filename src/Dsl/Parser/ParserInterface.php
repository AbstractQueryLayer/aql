<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface ParserInterface
{
    public function parse(string $code): NodeInterface;

    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface;
}
