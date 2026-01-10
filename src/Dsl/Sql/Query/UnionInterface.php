<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;

interface UnionInterface extends NodeInterface
{
    final public const string NODE_QUERIES = 'QUERIES';
    final public const string NODE_GROUP_BY = 'GROUP BY';
    final public const string NODE_ORDER_BY = 'ORDER BY';
    final public const string NODE_LIMIT = 'LIMIT';

    public function needParenthesis(): bool;

    public function addQuery(QueryInterface $query): static;

    public function getQueries(): NodeList;

    public function getGroupBy(): GroupByInterface;

    public function setUnionGroupBy(GroupByInterface $groupBy): static;

    /**
     * Add a group by expressions for UNION.
     *
     *
     */
    public function unionGroupBy(NodeInterface|string ...$nodes): static;

    public function getOrderBy(): OrderByInterface;

    public function setUnionOrderBy(OrderByInterface $orderBy): static;

    /**
     * Add an ORDER BY Asc expressions for UNION.
     *
     *
     * @return $this
     */
    public function unionOrderByAsc(NodeInterface|string ...$nodes): static;

    /**
     * Add an ORDER BY Desc expressions for UNION.
     *
     *
     * @return $this
     */
    public function unionOrderByDesc(NodeInterface|string ...$nodes): static;

    public function getLimit(): LimitInterface;

    public function setUnionLimit(LimitInterface $limit): static;

    /**
     * Add a LIMIT expression for UNION.
     *
     *
     * @return $this
     */
    public function unionLimit(int $limit, int $offset = 0): static;
}
