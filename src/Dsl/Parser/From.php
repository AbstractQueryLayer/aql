<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From as FromNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join as JoinNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject as SubjectNode;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

class From extends Join
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): FromNode|JoinInterface
    {
        if ($tokens->currentTokenAsString() !== 'FROM') {
            throw new ParseException('Expected FROM keyword');
        }

        return $this->parseTableReference($tokens->nextTokens(), true);
    }

    /**
     * Parse Table reference expression.
     *
     * @param bool            $allowFirstAsDerived If true first subject can be Derived Table
     * @throws ParseException
     */
    public function parseTableReference(TokensIteratorInterface $tokens, bool $allowFirstAsDerived = false): FromNode|JoinInterface
    {
        $stopTokens                 = $tokens->getStopTokens();
        $mainSubject                = null;
        $joins                      = [];
        $from                       = null;

        // Derived Tables detection:
        // SELECT ... FROM (subquery) [AS] tbl_name ...
        // see: https://dev.mysql.com/doc/refman/8.0/en/derived-tables.html
        if ($tokens->currentTokenAsString() === '(' && $allowFirstAsDerived) {
            $from                   = $this->parseFromAsSubquery($tokens, JoinInterface::FROM);
            $mainSubject            = $from->getSubject();

            // If the derived query has a closing parenthesis, we should return.
            if ($tokens->currentTokenAsString() === ')') {
                return $from;
            }

            if ($tokens->currentTokenAsString() === ',') {
                $tokens->nextTokens();
            }
        }

        while ($tokens->valid()) {

            [$type, $token, $line] = $tokens->currentToken();

            if (\array_key_exists(\strtolower((string) $token), $stopTokens)) {
                break;
            }

            if ($type !== T_STRING) {
                throw new ParseException(\sprintf('Expected EntityName in FROM expression (got "%s")', $token), ['line' => $line]);
            }

            if ($mainSubject === null) {
                $mainSubject        = new SubjectNode(\ucfirst((string) $token));
                [,$token]           = $tokens->nextToken();
            } elseif ($this->isJoinFirstKeyword($token)) {
                $joins[]            = (new Join())->parseTokens($tokens);
                [,$token]           = $tokens->currentToken();
            } else {
                $joins[]            = new JoinNode('', new SubjectNode(\ucfirst((string) $token)));
                [,$token]           = $tokens->nextToken();
            }

            if ($tokens->valid() === false) {
                break;
            }

            // Check exists also Entities?
            if ($token !== ',' && !$this->isJoinFirstKeyword($token)) {
                // end FROM expression
                break;
            } elseif ($token === ',') {
                $tokens->nextTokens();
            }
        }

        if ($mainSubject === null) {
            throw new ParseException('Expression FROM requires an EntityName');
        }

        if ($from !== null) {
            $from->applyDependentJoins(...$joins);
            return $from;
        }

        return new FromNode($mainSubject, ...$joins);
    }

    #[\Override]
    protected function nodeForSubquery(SubqueryInterface $subquery, string $joinType, string $alias): JoinInterface
    {
        return FromNode::newFromSubquery($subquery, $joinType, $alias);
    }
}
