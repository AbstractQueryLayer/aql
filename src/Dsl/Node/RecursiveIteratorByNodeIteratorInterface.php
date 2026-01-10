<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\DesignPatterns\Iterators\IteratorParentAwareInterface;

interface RecursiveIteratorByNodeIteratorInterface extends \Iterator, IteratorParentAwareInterface
{
    public function getCurrentTransformerIterator(): NodeTransformerIteratorInterface|null;
}
