<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\With as WithNode;

class With extends AqlParserAbstract
{
    /**
     * Allow parsing the WITH CTE statement.
     */
    public const string ALLOW_CTE = 'CTE';

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface
    {
        if ($tokens->currentTokenAsString() !== 'WITH') {
            throw new ParseException("WITH keyword required (got {$tokens->currentTokenAsString()})");
        }

        $tokens->throwIfOptionEmpty(self::ALLOW_CTE);

        $tokens->increaseRecursionDepth();

        $tokens->nextTokens();

        $isRecursive            = false;

        if ($tokens->currentTokenAsString() === 'RECURSIVE') {
            $tokens->nextTokens();

            $isRecursive        = true;
        }

        $subqueries            = [];

        /**
         * with_clause:
         * WITH [RECURSIVE]
         * cte_name AS (subquery)
         * [, cte_name AS (subquery)] ...
         */
        while ($tokens->valid()) {

            $cteName                = $this->exceptStringToken($tokens);

            if ($tokens->currentTokenAsString() !== 'AS') {
                throw new ParseException("AS keyword required (got {$tokens->currentTokenAsString()})");
            }

            $tokens->nextTokens();

            $subquery               = new Subquery()->parseTokens($tokens);
            $subquery->setCteAlias($cteName);

            $subqueries[]           = $subquery;

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextTokens();
        }

        $cte                        = new WithNode(...$subqueries);

        if ($isRecursive) {
            $cte->asRecursive();
        }

        // We can define
        if ($tokens->valid()) {
            // The Next token can be SELECT, INSERT, UPDATE, DELETE, etc.
            $cte->defineQuery(new AqlParser()->parseTokens($tokens));
        }

        $tokens->decreaseRecursionDepth();

        return $cte;
    }
}
