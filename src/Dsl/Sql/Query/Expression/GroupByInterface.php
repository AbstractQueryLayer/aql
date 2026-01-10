<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;

interface GroupByInterface extends NodeInterface, ChildNodeMutableInterface
{
    public function addGroupBy(NodeInterface $node): static;
}
