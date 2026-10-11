<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit as LimitNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitWithParameters;

/**
 * Parses `LIMIT count` and `LIMIT offset, count`, where each of the two is a number or a `{name}` parameter.
 *
 * A LIMIT of numbers only is a plain Limit node; one with a parameter is a LimitWithParameters node, which renders
 * the bound value as an integer and, when the LIMIT has no offset, as `LIMIT count`.
 */
class Limit extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): LimitInterface
    {
        if ($tokens->currentTokenAsString() !== 'LIMIT') {
            return new LimitNode();
        }

        $tokens->nextToken();

        $offset                     = new Constant(0);
        $limit                      = $this->parseValue($tokens);
        $hasOffset                  = false;

        if ($tokens->currentTokenAsString() === ',') {
            $tokens->nextToken();

            $offset                 = $limit;
            $limit                  = $this->parseValue($tokens);
            $hasOffset              = true;
        }

        if ($limit instanceof ParameterInterface || $offset instanceof ParameterInterface) {
            return new LimitWithParameters($limit, $offset, $hasOffset);
        }

        return new LimitNode($limit->getConstantValue(), $offset->getConstantValue());
    }

    /**
     * Reads the number or the parameter the iterator stands on and moves past it.
     *
     * @throws ParseException
     */
    private function parseValue(TokensIteratorInterface $tokens): ConstantInterface
    {
        [$type, $token, $line]      = $tokens->currentToken();

        if ($token === '{') {
            $parameter              = $this->parseOperand($tokens);

            if ($parameter instanceof ParameterInterface) {
                return $parameter;
            }
        }

        if ($type !== T_LNUMBER) {
            throw new ParseException(
                \sprintf('Expected Number or Parameter for LIMIT (got: \'%s\')', $token), ['line' => $line]
            );
        }

        $tokens->nextToken();

        return new Constant((int) $token);
    }
}
