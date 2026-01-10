<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface OrderByInterface extends NodeInterface
{
    public function addOrderItem(OrderItemInterface $orderItem): static;

    public function addAsc(NodeInterface $expression): static;

    public function addDesc(NodeInterface $expression): static;
}
