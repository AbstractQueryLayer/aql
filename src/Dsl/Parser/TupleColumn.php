<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn as TupleColumnNode;
use IfCastle\Exceptions\UnexpectedValue;

class TupleColumn extends AqlParserAbstract
{
    /**
     * @throws ParseException
     * @throws UnexpectedValue
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): TupleColumnNode
    {
        // SubQuery case
        if ($tokens->currentTokenAsString() === '(') {
            $expression             = (new SubqueryForResult())->parseTokens($tokens);
        } elseif ($tokens->currentTokenAsString() === '[') {
            $expression             = (new NestedTuple())->parseTokens($tokens);
        } else {
            $expression             = $this->parseOperand($tokens);
        }

        $alias                      = $this->parseAlias($tokens);

        return new TupleColumnNode($expression, $alias);
    }
}
