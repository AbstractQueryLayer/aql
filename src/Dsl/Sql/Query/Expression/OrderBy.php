<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

class OrderBy extends NodeAbstract implements OrderByInterface
{
    #[\Override]
    public function isEmpty(): bool
    {
        return $this->childNodes === [];
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return $this->childNodes !== [];
    }

    #[\Override]
    public function addOrderItem(OrderItemInterface $orderItem): static
    {
        $this->childNodes[]         = $orderItem->setParentNode($this);
        return $this;
    }

    #[\Override]
    public function addAsc(NodeInterface $expression): static
    {
        return $this->addOrderItem(new OrderItem($expression, OrderItemInterface::ASC));
    }

    #[\Override]
    public function addDesc(NodeInterface $expression): static
    {
        return $this->addOrderItem(new OrderItem($expression, OrderItemInterface::DESC));
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = $this->childNodesToAql(', ');

        if ($aql === '') {
            return '';
        }

        return 'ORDER BY ' . $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return 'ORDER BY ' . \implode(', ', $result);
    }
}
