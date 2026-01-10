<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Between as BetweenNode;

class Between extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): BetweenNode
    {
        if ($tokens->currentTokenAsString() !== BetweenNode::BETWEEN) {
            throw new ParseException('Expected ' . BetweenNode::BETWEEN . ' keyword');
        }

        // 1. Min
        $min                        = $this->parseOperand($tokens->nextTokens());

        // 2. Wait AND keyword
        if ($tokens->currentTokenAsString() !== 'AND') {
            throw new ParseException('Expected AND keyword for ' . BetweenNode::BETWEEN);
        }

        // 3. Max
        $max                        = $this->parseOperand($tokens->nextTokens());

        return new BetweenNode($min, $max);
    }
}
