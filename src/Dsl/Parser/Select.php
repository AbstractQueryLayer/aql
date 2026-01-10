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
        // 2. From and Joins expression
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
