<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join as JoinNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

class Join extends AqlParserAbstract
{
    public const string ALLOW_JOINS = 'JOIN';

    /**
     * Words that may follow a subject in FROM or JOIN and are therefore never taken for an alias without AS.
     */
    public const array WORDS_AFTER_SUBJECT = [
        'JOIN', 'INNER', 'LEFT', 'RIGHT', 'OUTER', 'CROSS', 'NATURAL', 'STRAIGHT_JOIN', 'SUBQUERY', 'ON', 'USING',
        'WHERE', 'ORDER', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'SET', 'FOR', 'LOCK', 'WITH', 'VALUES',
        'SELECT', 'INTO', 'USE', 'FORCE', 'IGNORE', 'PARTITION', 'WINDOW', 'RETURNING',
    ];
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

        $subject                    = null;
        $join                       = null;
        $subjectAlias               = null;
        $role                       = null;
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
            $tokens->nextToken();

            // 3a. Role path: JOIN owner.role
            $role                   = $this->parseRolePath($tokens, $subjectName);

            // 4. Subject alias: [AS] alias
            $subjectAlias           = $this->parseQualifier($tokens);

            // 4a. Index hints: USE INDEX (name)
            $subject                = new Subject($role === null ? \ucfirst($subjectName) : '');
            $this->parseIndexHints($tokens, $subject);
        }

        // 5. ON expression
        if ($tokens->currentTokenAsString() === 'ON') {

            [, $token, $line]       = $tokens->nextToken();

            if ($token !== '(') {
                throw new ParseException(
                    "Opening bracket '(' expected for ON expression (got '$token')", ['line' => $line]
                );
            }

            $conditions             = (new Conditions())->withBrackets()->asJoinConditions()->parseTokens($tokens);
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

        if ($subject !== null) {
            $join                   = new JoinNode($joinType, $subject);

            if ($role !== null) {
                $join->setRole(...$role);
            }

            if ($subjectAlias !== null || $role !== null) {
                // A role join without an alias is named by its role: `JOIN w.publishedRevision` → publishedRevision.x
                $join->setQualifier($subjectAlias ?? $role[1]);
                $join->setAlias($subjectAlias ?? $role[1]);
            }
        }

        if ($conditions !== null) {
            /* @var $conditions ConditionsInterface */
            $join->setConditions($conditions);
        }

        $join->applyDependentJoins(...$dependentJoins);

        $tokens->decreaseRecursionDepth();

        return $join;
    }

    /**
     * Parses `.role` after an owner alias: `JOIN owner.role` yields [owner, role], a plain subject yields null.
     *
     * @return array{0: string, 1: string}|null
     *
     * @throws ParseException
     */
    protected function parseRolePath(TokensIteratorInterface $tokens, string $owner): ?array
    {
        if ($tokens->currentTokenAsString() !== '.') {
            return null;
        }

        [$type, $token, $line]      = $tokens->nextToken();

        if ($type !== T_STRING) {
            throw new ParseException(
                \sprintf('Expected a role name after \'%s.\' (got \'%s\')', $owner, $token), ['line' => $line]
            );
        }

        $tokens->nextToken();

        return [$owner, $token];
    }

    /**
     * Parses index hints after a subject and its alias: any number of `USE|FORCE|IGNORE INDEX (name[, name])`.
     *
     * @throws ParseException
     */
    protected function parseIndexHints(TokensIteratorInterface $tokens, SubjectInterface $subject): void
    {
        while (\in_array($tokens->currentTokenAsString(), SubjectInterface::INDEX_HINTS, true)) {
            $hint                   = $tokens->currentTokenAsString();
            $tokens->nextTokens()->assertTokenIs('INDEX')->assertTokenIs('(');
            $subject->addIndexHint($hint, ...$this->parseIndexNames($tokens));
        }
    }

    /**
     * Parses `name[, name])` of an index hint, the closing bracket included.
     *
     * @return list<string>
     *
     * @throws ParseException
     */
    protected function parseIndexNames(TokensIteratorInterface $tokens): array
    {
        $names                      = [];

        do {
            [$type, $token, $line]  = $tokens->currentToken();

            if ($type !== T_STRING) {
                throw new ParseException(\sprintf('Expected an index name (got \'%s\')', $token), ['line' => $line]);
            }

            $names[]                = $token;
            $tokens->nextTokens();
        } while ($tokens->checkAndConsumeToken(','));

        $tokens->assertTokenIs(')');

        return $names;
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

        $subquery                   = (new Subquery())->parseTokens($tokens->nextTokens());

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

    /**
     * Parses an optional `[AS] alias` after a subject. Without AS, a word is an alias unless it is a keyword that
     * may follow a subject or one of the iterator's stop tokens.
     *
     * @throws ParseException
     */
    public function parseQualifier(TokensIteratorInterface $tokens): ?string
    {
        [$type, $token, $line]      = $tokens->currentToken();

        if ($type === false) {
            return null;
        }

        if ($token === 'AS') {

            [$type, $token, $line]  = $tokens->nextToken();

            if ($type !== T_STRING) {
                throw new ParseException(\sprintf('Expected an alias after AS (got \'%s\')', $token), ['line' => $line]);
            }

            $tokens->nextTokens();

            return $token;
        }

        if ($type !== T_STRING
            || \in_array(\strtoupper((string) $token), self::WORDS_AFTER_SUBJECT, true)
            || \array_key_exists(\strtolower((string) $token), $tokens->getStopTokens())) {
            return null;
        }

        $tokens->nextTokens();

        return $token;
    }

    public function isJoinFirstKeyword(string $token): bool
    {
        return \in_array($token, ['JOIN', 'INNER', 'LEFT', 'SUBQUERY'], true);
    }
}
