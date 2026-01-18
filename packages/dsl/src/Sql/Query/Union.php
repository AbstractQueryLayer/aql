<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderItemInterface;

class Union extends NodeAbstract implements UnionInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->initChildNodes();
    }

    protected function initChildNodes(): void
    {
        $this->childNodes[self::NODE_QUERIES]     = new NodeList()->setParentNode($this);
        $this->childNodes[self::NODE_GROUP_BY]    = new GroupBy()->setParentNode($this);
        $this->childNodes[self::NODE_ORDER_BY]    = new OrderBy()->setParentNode($this);
        $this->childNodes[self::NODE_LIMIT]       = new Limit()->setParentNode($this);
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->getQueries()->isEmpty();
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return $this->getQueries()->isNotEmpty();
    }

    #[\Override]
    public function needParenthesis(): bool
    {
        if ($this->getGroupBy()->isNotEmpty()) {
            return true;
        }
        if ($this->getOrderBy()->isNotEmpty()) {
            return true;
        }
        return $this->getLimit()->isNotEmpty();
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $results                    = [];

        foreach ($this->childNodes[self::NODE_QUERIES] as $node) {

            if (false === $node instanceof SelectInterface) {
                continue;
            }

            $aql                    = $node->getAql($forResolved);

            if ($aql !== '') {
                $results[]          = $aql;
            }
        }

        $nodes = $this->childNodes;
        unset($nodes[self::NODE_QUERIES]);

        $results[]                  = $this->nodesToAql($nodes, "\n", $forResolved);

        return \implode("\n", $results);
    }

    #[\Override]
    protected function generateResult(): string
    {
        $results                    = [];

        foreach ($this->childNodes[self::NODE_QUERIES] as $node) {

            if (false === $node instanceof SelectInterface) {
                continue;
            }

            $sql                    = $node->getResult();

            if ($sql !== '') {
                $results[]          = $sql;
            }
        }

        $nodes                      = $this->childNodes;
        unset($nodes[self::NODE_QUERIES]);

        foreach ($nodes as $childNode) {

            if ($childNode instanceof NodeInterface) {
                $result             = $childNode->getResult();

                if (!empty($result)) {
                    $results[]      = $result;
                }
            }
        }

        return \implode("\n", $results);
    }

    #[\Override]
    public function getQueries(): NodeList
    {
        return $this->childNodes[self::NODE_QUERIES];
    }

    #[\Override]
    public function addQuery(QueryInterface $query): static
    {
        $this->getQueries()->addChildNode($query);

        return $this;
    }

    #[\Override]
    public function getGroupBy(): GroupByInterface
    {
        return $this->childNodes[self::NODE_GROUP_BY];
    }

    #[\Override]
    public function setUnionGroupBy(GroupByInterface $groupBy): static
    {
        $this->childNodes[self::NODE_GROUP_BY] = $groupBy->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function unionGroupBy(string|NodeInterface ...$nodes): static
    {
        $this->getGroupBy()->addChildNode(...$nodes);

        return $this;
    }

    #[\Override]
    public function getOrderBy(): OrderByInterface
    {
        return $this->childNodes[self::NODE_ORDER_BY];
    }

    #[\Override]
    public function setUnionOrderBy(OrderByInterface $orderBy): static
    {
        $this->childNodes[self::NODE_ORDER_BY] = $orderBy->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function unionOrderByAsc(string|NodeInterface ...$nodes): static
    {
        foreach ($nodes as $node) {
            if ($node instanceof OrderItemInterface) {
                $this->getOrderBy()->addOrderItem($node);
            } elseif (\is_string($node)) {
                $this->getOrderBy()->addAsc(new Column($node));
            } else {
                $this->getOrderBy()->addAsc($node);
            }
        }

        return $this;
    }

    #[\Override]
    public function unionOrderByDesc(string|NodeInterface ...$nodes): static
    {
        foreach ($nodes as $node) {
            if ($node instanceof OrderItemInterface) {
                $this->getOrderBy()->addOrderItem($node);
            } elseif (\is_string($node)) {
                $this->getOrderBy()->addDesc(new Column($node));
            } else {
                $this->getOrderBy()->addDesc($node);
            }
        }

        return $this;
    }

    #[\Override]
    public function getLimit(): LimitInterface
    {
        return $this->childNodes[self::NODE_LIMIT];
    }

    #[\Override]
    public function setUnionLimit(LimitInterface $limit): static
    {
        $this->childNodes[self::NODE_LIMIT] = $limit->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function unionLimit(int $limit, int $offset = 0): static
    {
        $this->getLimit()->setLimit($limit)->setOffset($offset);
        return $this;
    }
}
