<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\DesignPatterns\Iterators\IteratorParentAwareInterface;
use IfCastle\DesignPatterns\Iterators\IteratorWithPathInterface;

interface NodeTransformerIteratorInterface extends \RecursiveIterator, IteratorWithPathInterface, IteratorParentAwareInterface
{
    public function currentNode(): NodeInterface|null;
}
