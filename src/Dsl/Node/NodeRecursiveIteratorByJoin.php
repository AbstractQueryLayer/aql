<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;

/**
 * ## NodeRecursiveIteratorByJoin
 * Walks through the tree of Join-nodes by subject component.
 */
class NodeRecursiveIteratorByJoin implements \Iterator, \RecursiveIterator
{
    protected mixed $current        = null;

    protected int $index            = 0;

    protected readonly array $childNodes;

    public function __construct(JoinInterface ...$childNodes)
    {
        $this->childNodes           = $childNodes;
    }

    #[\Override]
    public function current(): mixed
    {
        return $this->current;
    }

    public function getCurrentJoin(): JoinInterface|null
    {
        return $this->current;
    }

    #[\Override]
    public function key(): mixed
    {
        return $this->index;
    }

    #[\Override]
    public function valid(): bool
    {
        return $this->current !== null;
    }

    #[\Override]
    public function rewind(): void
    {
        $this->index                = 0;

        if ($this->childNodes !== []) {
            $this->current      = $this->childNodes[0];
        }
    }

    #[\Override]
    public function next(): void
    {
        ++$this->index;

        if ($this->index >= \count($this->childNodes)) {
            $this->current          = null;
            return;
        }

        $this->current              = $this->childNodes[$this->index];
    }

    #[\Override]
    public function getChildren(): ?\RecursiveIterator
    {
        if ($this->current instanceof JoinInterface) {
            return new self(...\array_values($this->current->getChildJoins()));
        }

        return null;
    }

    #[\Override]
    public function hasChildren(): bool
    {
        return $this->current instanceof JoinInterface && $this->current->hasChildJoins();
    }
}
