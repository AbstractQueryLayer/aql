<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;

/**
 * ## NodeRecursiveIteratorBySubject
 * Walks through the tree of Join-nodes by subject component.
 */
final class NodeRecursiveIteratorBySubject extends NodeRecursiveIteratorByJoin
{
    #[\Override]
    public function current(): mixed
    {
        if ($this->current instanceof JoinInterface) {
            return $this->current->getSubject();
        }

        return $this->current;
    }

    #[\Override] public function getChildren(): ?\RecursiveIterator
    {
        if ($this->current instanceof JoinInterface) {
            return new self(...\array_values($this->current->getChildJoins()));
        }

        return null;
    }
}
