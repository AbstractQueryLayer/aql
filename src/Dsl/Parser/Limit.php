<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit as LimitNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;

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

        $offset                     = 0;

        [$type, $token, $line]      = $tokens->nextToken();

        if ($type !== T_LNUMBER) {
            throw new ParseException('Expected Number for LIMIT ' . \sprintf(' (got: \'%s\')', $token), ['line' => $line]);
        }

        $limit                      = (int) $token;

        // look next expression
        $tokens->nextToken();

        if ($tokens->currentTokenAsString() === ',') {

            [$type, $token, $line]  = $tokens->nextToken();

            if ($type !== T_LNUMBER) {
                throw new ParseException('Expected Number for LIMIT ' . \sprintf(' (got: \'%s\')', $token), ['line' => $line]);
            }

            $offset                 = $limit;
            $limit                  = (int) $token;

            $tokens->nextToken();
        }

        return new LimitNode($limit, $offset);
    }
}
