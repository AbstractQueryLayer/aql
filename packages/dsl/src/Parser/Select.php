<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\QueryOption;
use IfCastle\AQL\Dsl\QueryOptions;
use IfCastle\AQL\Dsl\QueryOptionsInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where as WhereNode;
use IfCastle\AQL\Dsl\Sql\Query\LockMode;
use IfCastle\AQL\Dsl\Sql\Query\LockWait;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\Select as SelectNode;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Dsl\Sql\Query\UnionEnum;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\Exceptions\RequiredValueEmpty;
use IfCastle\Exceptions\UnexpectedValue;

class Select extends AqlParserAbstract
{
    public const string ALLOW_UNION = 'UNION';

    protected bool $isPrimaryQuery  = true;

    protected bool $isMainParenthesis = false;

    protected bool $isQueryParenthesis = false;

    /**
     * Whether SELECT may omit FROM: `SELECT expression [AS alias], ...`, a row of expressions the server computes.
     * A subquery and a nested tuple need an entity to read, so they keep FROM required.
     */
    protected bool $allowWithoutFrom = true;

    /**
     * The method marks the SELECT query as the primary one.
     * The primary query has the right to capture UNION tokens and others without parentheses.
     *
     * @return $this
     */
    public function asPrimaryQuery(): self
    {
        $this->isPrimaryQuery       = true;
        return $this;
    }

    public function asNotPrimaryQuery(): self
    {
        $this->isPrimaryQuery       = false;
        return $this;
    }

    /**
     * @throws UnexpectedValue
     * @throws RequiredValueEmpty
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): QueryInterface
    {
        //
        // Expression like: ((SELECT UNION ...) ORDER BY ...)
        //
        $this->isMainParenthesis    = $tokens->currentTokenAsString() === '(';

        if ($this->isMainParenthesis) {
            $tokens->nextTokens();
        }

        //
        // Expression like: (SELECT ...) UNION (SELECT...)
        //
        $this->isQueryParenthesis   = $tokens->currentTokenAsString() === '(';
        if ($this->isQueryParenthesis) {
            $tokens->nextTokens();
        }

        if ($tokens->currentTokenAsString() !== QueryInterface::ACTION_SELECT) {
            throw new ParseException('Expected keyword ' . QueryInterface::ACTION_SELECT . ' (got: "'
                                    . $tokens->currentTokenAsString() . '")', ['line' => $tokens->getCurrentLine()]);
        }

        $result                     = $this->parseSelect($tokens->nextTokens());

        if ($this->isMainParenthesis) {

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException('Expected main parenthesis ")"', ['line' => $tokens->getCurrentLine()]);
            }

            $tokens->nextTokens();
        }

        return $result;
    }

    /**
     * @throws UnexpectedValue
     * @throws ParseException
     * @throws RequiredValueEmpty
     */
    public function parseSelect(TokensIteratorInterface $tokens): QueryInterface
    {
        $options                    = $this->parseOptions($tokens);

        $oldStopTokens              = $tokens->getStopTokens();

        $tokens->addStopTokens([QueryInterface::NODE_FROM]);

        // 1. Columns expression
        $tuple                      = (new Tuple())->parseTokens($tokens);
        $query                      = $this->allowWithoutFrom && $tokens->currentTokenAsString() !== 'FROM'
                                    ? $this->parseSelectWithoutFrom($tokens, $tuple, $options)
                                    : $this->parseSelectFrom($tokens, $tuple, $options);

        // restore oldStopTokens
        $tokens->setStopTokens($oldStopTokens);

        if ($this->isQueryParenthesis) {

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException('Expected query parenthesis ")"', ['line' => $tokens->getCurrentLine()]);
            }

            $tokens->nextTokens();
        }

        if ($this->isQueryParenthesis    === false
           && $this->isMainParenthesis  === true
           && $tokens->currentTokenAsString() === ')') {
            $tokens->nextTokens();
            $this->isQueryParenthesis = true;
            $this->isMainParenthesis  = false;
        }

        // If the query is not the primary one, then we do not parse UNION, INTERSECT, EXCEPT
        if (false === $this->isMainParenthesis && false === $this->isPrimaryQuery) {
            return $query;
        }

        if (\in_array($tokens->currentTokenAsString(), ['UNION', 'INTERSECT', 'EXCEPT'], true)) {

            if (false === $query instanceof SelectInterface) {
                throw new ParseException(
                    'The UNION expression can only be used with SELECT queries (got: "'
                    . $query->getQueryAction() . '")', ['line' => $tokens->getCurrentLine()],
                );
            }

            $this->parseUnion($tokens, $query);
        }

        return $query;
    }

    /**
     * Parses the clauses after the columns: FROM with joins, WHERE, GROUP BY, ORDER BY, LIMIT and a locking read.
     *
     * @throws UnexpectedValue
     * @throws ParseException
     * @throws RequiredValueEmpty
     */
    private function parseSelectFrom(
        TokensIteratorInterface $tokens,
        TupleInterface $tuple,
        QueryOptionsInterface $options
    ): QueryInterface {
        $tokens->addStopTokens([QueryInterface::NODE_WHERE,
            QueryInterface::NODE_ORDER_BY,
            QueryInterface::NODE_GROUP_BY,
            QueryInterface::NODE_LIMIT,
            QueryInterface::NODE_UNION]);

        $from                       = (new From())->parseTokens($tokens);
        // 3. Where expression
        $where                      = (new Where())->parseTokens($tokens);
        // 4. Group by
        $groupBy                    = (new GroupBy())->parseTokens($tokens);
        // 5. Order By
        $orderBy                    = (new OrderBy())->parseTokens($tokens);
        // 6. Limit
        $limit                      = (new Limit())->parseTokens($tokens);
        // 7. Locking read: the last clause of the SELECT
        $lockMode                   = $this->parseLockMode($tokens);
        $lockWait                   = $lockMode === null ? null : $this->parseLockWait($tokens);

        // Build a query

        /* @var $from JoinInterface */
        /* @var $tuple TupleInterface */
        /* @var $where Where */
        /* @var $limit LimitInterface */
        /* @var $options QueryOptionsInterface */
        /* @var $orderBy OrderByInterface */
        /* @var $groupBy GroupByInterface */

        $query                      = $this->newSelectNode($from, $tuple, $where, $limit)->setQueryOptions($options);

        $query->setOrderBy($orderBy);

        $query->setGroupBy($groupBy);

        if ($query instanceof SelectInterface) {
            $query->setLockMode($lockMode)->setLockWait($lockWait);
        } elseif ($lockMode !== null) {
            throw new ParseException(
                $lockMode->value . ' needs a SELECT node, the parser built ' . $query::class,
                ['line' => $tokens->getCurrentLine()]
            );
        }

        return $query;
    }

    /**
     * A SELECT without FROM is a row of expressions with optional aliases and nothing else, so any clause after
     * them is refused here instead of being rendered into SQL that has no table to apply it to.
     *
     * @throws ParseException when the SELECT has no expression, `*`, or a clause other than UNION follows
     */
    private function parseSelectWithoutFrom(
        TokensIteratorInterface $tokens,
        TupleInterface $tuple,
        QueryOptionsInterface $options
    ): QueryInterface {
        if ($tuple->isDefaultColumns() || $tuple->getTupleColumns() === []) {
            throw new ParseException(
                'SELECT without FROM needs expressions to compute (got: "'
                . $tokens->currentTokenAsString() . '")', ['line' => $tokens->getCurrentLine()]
            );
        }

        $next                       = $tokens->currentTokenAsString();

        if ($tokens->valid() && !\in_array($next, [')', 'UNION', 'INTERSECT', 'EXCEPT'], true)) {
            throw new ParseException(
                'SELECT without FROM takes expressions only: expected the end of the query (got: "'
                . $next . '")', ['line' => $tokens->getCurrentLine()]
            );
        }

        return (new SelectNode(null, $tuple))->setQueryOptions($options);
    }

    /**
     * Parses the locking-read clause `FOR UPDATE` or `LOCK IN SHARE MODE`; null when the SELECT has none.
     *
     * @throws ParseException when `FOR` or `LOCK` does not start one of the two clauses
     */
    private function parseLockMode(TokensIteratorInterface $tokens): ?LockMode
    {
        $mode                       = match ($tokens->currentTokenAsString()) {
            'FOR'                   => LockMode::FOR_UPDATE,
            'LOCK'                  => LockMode::SHARE,
            default                 => null,
        };

        if ($mode === null) {
            return null;
        }

        $this->expectPhrase($tokens, $mode->value);

        return $mode;
    }

    /**
     * Parses `SKIP LOCKED` or `NOWAIT` after a locking-read clause; null when neither follows.
     *
     * @throws ParseException when `SKIP` is not followed by `LOCKED`
     */
    private function parseLockWait(TokensIteratorInterface $tokens): ?LockWait
    {
        $wait                       = match ($tokens->currentTokenAsString()) {
            'SKIP'                  => LockWait::SKIP_LOCKED,
            'NOWAIT'                => LockWait::NOWAIT,
            default                 => null,
        };

        if ($wait === null) {
            return null;
        }

        $this->expectPhrase($tokens, $wait->value);

        return $wait;
    }

    /**
     * Consumes the words of $phrase, separated by single spaces, from the current token on.
     *
     * @throws ParseException when a token differs from the next word
     */
    private function expectPhrase(TokensIteratorInterface $tokens, string $phrase): void
    {
        foreach (\explode(' ', $phrase) as $word) {
            [, $token]              = $tokens->currentToken();

            if (\strtoupper((string) $token) !== $word) {
                throw new ParseException(
                    'Expected ' . $phrase . ' (got: "' . $token . '")', ['line' => $tokens->getCurrentLine()]
                );
            }

            $tokens->nextToken();
        }
    }

    /**
     * Parse UNION, INTERSECT, EXCEPT expressions.
     * @throws ParseException|UnexpectedValue
     */
    protected function parseUnion(TokensIteratorInterface $tokens, SelectInterface $mainSelect): void
    {
        $tokens->throwIfOptionEmpty(self::ALLOW_UNION);

        do {
            $unionType              = match ($tokens->currentTokenAsString()) {
                'UNION'             => UnionEnum::UNION,
                'INTERSECT'         => UnionEnum::INTERSECT,
                'EXCEPT'            => UnionEnum::EXCEPT,
                default             => throw new ParseException('Unknown union type: "'
                                                                . $tokens->currentTokenAsString() . '"',
                    ['line' => $tokens->getCurrentLine()],
                ),
            };

            $tokens->nextTokens();

            $unionOption            = match ($tokens->currentTokenAsString()) {
                'ALL'               => UnionEnum::ALL,
                'DISTINCT'          => UnionEnum::DISTINCT,
                default             => null,
            };

            if ($unionOption !== null) {
                $tokens->nextTokens();
            }

            $tokens->increaseRecursionDepth();
            $subquery               = (new self())->asNotPrimaryQuery()->parseTokens($tokens);
            $tokens->decreaseRecursionDepth();

            if (false === $subquery instanceof SelectInterface) {
                throw new ParseException(
                    'The UNION expression can only be used with SELECT queries (got: "'
                    . $subquery->getQueryAction() . '")', ['line' => $tokens->getCurrentLine()],
                );
            }

            $mainSelect->getUnion()->addQuery($subquery->asUnion($unionType, $unionOption));

        } while (\in_array($tokens->currentTokenAsString(), ['UNION', 'INTERSECT', 'EXCEPT'], true));

        // Try to parse GROUP BY, ORDER BY, LIMIT for UNION expression
        if ($tokens->currentTokenAsString() === 'GROUP') {
            $mainSelect->getUnion()->setUnionGroupBy((new GroupBy())->parseTokens($tokens));
        }

        if ($tokens->currentTokenAsString() === 'ORDER') {
            $mainSelect->getUnion()->setUnionOrderBy((new OrderBy())->parseTokens($tokens));
        }

        if ($tokens->currentTokenAsString() === 'LIMIT') {
            $mainSelect->getUnion()->setUnionLimit((new Limit())->parseTokens($tokens));
        }
    }

    /**
     * Returns array of strings as a query options
     * The AQL option should be started with ":".
     *
     *
     *
     * @throws  ParseException
     */
    public function parseOptions(TokensIterator $tokens): QueryOptionsInterface
    {
        $options                    = [];

        while ($tokens->currentTokenAsString() === ':') {

            [$type, $token, $line]  = $tokens->nextToken();

            if ($type !== T_STRING) {
                throw new ParseException('Option must be a string', ['line' => $line]);
            }

            $options[]              = new QueryOption($token);

            $tokens->nextTokens();
        }

        return new QueryOptions(...$options);
    }

    protected function newSelectNode(JoinInterface|string                     $from,
        TupleInterface|array|null                $tuple     = null,
        WhereNode|ConditionsInterface|array|null $where = null,
        ?LimitInterface                           $limit     = null): QueryInterface
    {
        return new SelectNode($from, $tuple, $where, $limit);
    }
}
