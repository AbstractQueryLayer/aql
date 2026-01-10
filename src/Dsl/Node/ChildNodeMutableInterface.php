<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

interface ChildNodeMutableInterface
{
    public function addChildNode(NodeInterface ...$nodes): void;
}
