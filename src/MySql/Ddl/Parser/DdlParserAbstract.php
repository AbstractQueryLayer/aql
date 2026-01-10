<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\ParserInterface;
use IfCastle\AQL\Dsl\Parser\TokensIterator;

abstract class DdlParserAbstract implements ParserInterface
{
    #[\Override]
    public function parse(string $code): NodeInterface
    {
        return $this->parseTokens(new TokensIterator($code));
    }
}
