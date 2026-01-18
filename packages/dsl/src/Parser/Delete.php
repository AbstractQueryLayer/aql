<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Delete as DeleteNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Using;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

class Delete extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): DeleteNode
    {
        if ($tokens->currentTokenAsString() !== QueryInterface::ACTION_DELETE) {
            throw new ParseException('Expected keyword ' . QueryInterface::ACTION_DELETE, ['line' => $tokens->getCurrentLine()]);
        }

        $tokens->increaseRecursionDepth();

        $oldStopTokens              = $tokens->getStopTokens();
        $tokens->addStopTokens([
            QueryInterface::NODE_WHERE, QueryInterface::NODE_ORDER_BY,
            QueryInterface::NODE_GROUP_BY, QueryInterface::NODE_LIMIT,
            QueryInterface::NODE_UNION,
        ]);

        // 2. Parse Using expression
        // DELETE t1, t2 FROM t1 INNER JOIN t2 INNER JOIN t3
        // WHERE t1.id=t2.id AND t2.id=t3.id;
        $using                      = $this->parseUsing($tokens->nextTokens());

        // 3. From and Joins expression
        $from                       = new From()->parseTokens($tokens);

        // 4. WHERE
        $where                      = new Where()->parseTokens($tokens);
        // 5. Order by
        $orderBy                    = new OrderBy()->parseTokens($tokens);
        // 6. Limit
        $limit                      = new Limit()->parseTokens($tokens);

        $tokens->setStopTokens($oldStopTokens);

        $query                      = new DeleteNode($from, $where, $limit);

        if ($using !== null) {
            $query->setUsing($using);
        }

        $query->setOrderBy($orderBy);

        $tokens->decreaseRecursionDepth();

        return $query;
    }

    /**
     * @throws ParseException
     */
    protected function parseUsing(TokensIteratorInterface $tokens): ?Using
    {
        $stopTokens                                        = $tokens->getStopTokens();
        $stopTokens[\strtolower(QueryInterface::NODE_FROM)] = true;

        $aliasList                  = [];

        while (false === \array_key_exists($tokens->currentTokenAsString(true), $stopTokens)) {

            [$type, $token, $line]  = $tokens->currentToken();

            if ($type !== T_STRING) {
                throw new ParseException(
                    \sprintf('Expected Alias for USING expression (got \'%s\')', $token), ['line' => $line]
                );
            }

            $aliasList[]            = $token;

            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextTokens();
        }

        return new Using(...$aliasList);
    }
}
