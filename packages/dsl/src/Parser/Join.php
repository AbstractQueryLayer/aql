<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join as JoinNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

class Join extends AqlParserAbstract
{
    public const string ALLOW_JOINS = 'JOIN';

    public const string ALLOW_JOIN_CONDITIONS = 'ON';

    public const string ALLOW_JOIN_DEPENDENT = 'Dependent Joins';

    public const string ALLOW_DERIVED_TABLE = 'Derived Table';

    public const string ALLOW_FROM_SELECT = 'FROM SELECT';

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): JoinInterface
    {
        $tokens->increaseRecursionDepth();

        $joinType                   = null;

        // 1. First keywords should be...
        $firstToken                 = $tokens->currentTokenAsString();

        if (!$this->isJoinFirstKeyword($firstToken)) {
            throw new ParseException('Expected JOIN|INNER|LEFT|SUBQUERY keyword');
        }

        $tokens->nextToken();

        if ($firstToken !== 'JOIN' && $firstToken !== 'SUBQUERY') {

            $joinType               = $firstToken;

            if ($tokens->currentTokenAsString() !== 'JOIN') {
                throw new ParseException(\sprintf('Expected JOIN keyword after \'%s\'', $firstToken));
            }

            $tokens->nextToken();
        } elseif ($firstToken === 'JOIN') {
            $joinType               = JoinInterface::INNER;
        }

        $subjectName                = null;
        $join                       = null;
        $subjectAlias               = null;
        $conditions                 = null;
        $dependentJoins             = [];

        // 2. Derived Tables detection:
        // SELECT ... FROM (subquery) [AS] tbl_name ...
        // see: https://dev.mysql.com/doc/refman/8.0/en/derived-tables.html
        if ($tokens->currentTokenAsString() === '(') {
            $join                   = $this->parseFromAsSubquery($tokens, $joinType);
        } else {
            // 3. Subject
            [$type, $token, $line]  = $tokens->currentToken();

            if ($type !== T_STRING) {
                throw new ParseException(\sprintf('Expected EntityName in JOIN expression (got \'%s\')', $token), ['line' => $line]);
            }

            $subjectName            = $token;

            // 4. Subject alias
            $tokens->nextToken();

            if ($tokens->currentTokenAsString() === 'AS') {

                [$type, $token, $line] = $tokens->nextToken();

                if ($type !== T_STRING) {
                    throw new ParseException(\sprintf('Expected EntityName alias (got \'%s\')', $token), ['line' => $line]);
                }

                $subjectAlias       = $token;

                $tokens->nextTokens();
            }
        }

        // 5. ON expression
        if ($tokens->currentTokenAsString() === 'ON') {

            [, $token, $line]       = $tokens->nextToken();

            if ($token !== '(') {
                throw new ParseException(
                    "Opening bracket '(' expected for ON expression (got '$token')", ['line' => $line]
                );
            }

            $conditions             = new Conditions()->withBrackets()->asJoinConditions()->parseTokens($tokens);
        }

        // 6. Dependent Joins expression LIKE ODBC:
        // INNER JOIN Parent {
        //      INNER JOIN Child1 ON (...)
        //      LEFT JOIN Child2 {
        //          INNER JOIN Child2_1
        //      }
        // }
        //
        // it means: Child1 relation to Parent
        //           Child2 relation to Parent
        //           Child2_1 relation to Child2
        //
        if ($tokens->currentTokenAsString() === '{') {

            $dependentJoins         = static::parseDependentJoins($tokens->nextTokens());

            if ($tokens->currentTokenAsString() !== '}') {
                throw new ParseException(
                    "Closing bracket '}' expected for dependent joins (got '$token')", ['line' => $line]
                );
            }

            $tokens->nextTokens();
        }

        $join                       ??= new JoinNode(
            $joinType, new Subject(\ucfirst($subjectName), '', $subjectAlias ?? '')
        );

        if ($subjectAlias !== null) {
            $join->setAlias($subjectAlias);
        }

        if ($conditions !== null) {
            /* @var $conditions ConditionsInterface */
            $join->setConditions($conditions);
        }

        $join->applyDependentJoins(...$dependentJoins);

        $tokens->decreaseRecursionDepth();

        return $join;
    }

    public function parseDependentJoins(TokensIterator $tokens): array
    {
        $tokens->throwIfOptionEmpty(self::ALLOW_JOIN_DEPENDENT);

        $joins                      = [];

        while ($this->isJoinFirstKeyword($tokens->currentTokenAsString())) {
            $joins[]                = $this->parseTokens($tokens);
        }

        return $joins;
    }

    /**
     * @throws ParseException
     */
    public function parseFromAsSubquery(TokensIteratorInterface $tokens, string $joinType): JoinInterface
    {
        if ($tokens->currentTokenAsString() !== '(') {
            throw new ParseException('The subquery must start with "("', ['line' => $tokens->getCurrentLine()]);
        }

        $tokens->throwIfOptionEmpty(self::ALLOW_FROM_SELECT);

        $subquery                   = new Subquery()->parseTokens($tokens->nextTokens());

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException('Close bracket expected for Subquery expression', ['line' => $tokens->getCurrentLine()]);
        }

        $tokens->nextTokens();

        //
        // Try parse "as alias" expression
        // The [AS] tbl_name clause is mandatory because every table in a FROM clause must have a name.
        //
        if ($tokens->currentTokenAsString() === 'AS') {
            $tokens->nextTokens();
        }

        [$type, $token, $line]      = $tokens->currentToken();

        if ($type !== T_STRING) {
            throw new ParseException("Expected Subquery alias (got '$token')", ['line' => $line]);
        }

        $tokens->nextTokens();

        /** @var $subquery SubqueryInterface  */
        return $this->nodeForSubquery($subquery, $joinType, $token);
    }

    protected function nodeForSubquery(SubqueryInterface $subquery, string $joinType, string $alias): JoinInterface
    {
        return JoinNode::newFromSubquery($subquery, $joinType, $alias);
    }

    public function isJoinFirstKeyword(string $token): bool
    {
        return \in_array($token, ['JOIN', 'INNER', 'LEFT', 'SUBQUERY'], true);
    }
}
