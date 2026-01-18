<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupBy as GroupByNode;
use IfCastle\Exceptions\UnexpectedValue;

class GroupBy extends AqlParserAbstract
{
    /**
     * @throws UnexpectedValue
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): GroupByNode
    {
        // Expression: ORDER BY expression1, expression2 ASC, expression3 DESC
        if ($tokens->currentTokenAsString() !== 'GROUP') {
            return new GroupByNode();
        }

        $tokens->nextToken();

        if ($tokens->currentTokenAsString() !== 'BY') {
            throw new ParseException('Expected GROUP BY expression');
        }

        $tokens->nextToken();

        $stopTokens                 = $tokens->getStopTokens();
        $expressions                = [];

        while (true) {
            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            $expressions[]          = $this->parseOperand($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            } elseif ($tokens->valid()) {
                $tokens->nextToken();
            }
        }

        if ($expressions === []) {
            throw new ParseException('Empty GROUP BY expression is not allowed');
        }

        return new GroupByNode(...$expressions);
    }
}
