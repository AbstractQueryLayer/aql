<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\InExpression as InExpressionNode;
use IfCastle\Exceptions\UnexpectedValue;

class InExpression extends AqlParserAbstract
{
    /**
     * @throws UnexpectedValue
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface
    {
        $tokens->increaseRecursionDepth();

        if ($tokens->currentTokenAsString() === InExpressionNode::THE) {
            return new InExpressionNode(new Subquery()->parseTheExpression($tokens->nextTokens()));
        }

        if ($tokens->currentTokenAsString() !== '(') {
            throw new ParseException(\sprintf('Expected operator \'(\' for IN (...), got \'%s\'', $tokens->currentTokenAsString()));
        }

        $tokens->nextTokens();

        if ($tokens->currentTokenAsString() === 'SELECT') {
            $query                  = new Subquery()->parseTokens($tokens);

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException("Expected operator ')' for IN (...), got '{$tokens->currentTokenAsString()}'");
            }

            $tokens->nextTokens();

            $tokens->decreaseRecursionDepth();

            return new InExpressionNode($query);
        }

        $stopTokens                 = [')'];

        $results                    = [];

        while ($tokens->valid() && !\array_key_exists(\strtolower($tokens->currentTokenAsString()), $stopTokens)) {

            $results[]              = new Constant()->parseTokens($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextToken();

        }

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException("Expected operator ')' for IN (...), got '{$tokens->currentTokenAsString()}'");
        }

        $tokens->nextTokens();

        $tokens->decreaseRecursionDepth();

        return new InExpressionNode($results);
    }
}
