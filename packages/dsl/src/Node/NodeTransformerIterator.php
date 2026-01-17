<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use RecursiveIterator;

/**
 * ## NodeNormalizerIterator
 * provides traversal through child nodes by algorithm:
 * 1. If the current node has a substitution, it is resolved and returned.
 * 2. If the current node has child nodes, the first child node is returned.
 * 3. If the node is not normalized, it is returned.
 *
 */
class NodeTransformerIterator implements NodeTransformerIteratorInterface
{
    protected ?array $childNodes    = null;

    protected ?NodeInterface $current = null;

    protected int $index            = 0;

    protected \WeakReference|null $parent = null;

    protected bool $isSelfPassed    = false;

    /**
     * Create an iterator for the node.
     * If $current is provided, the iterator will traverse only the $current node and its children.
     */
    public function __construct(
        protected NodeInterface $node,
        ?NodeTransformerIteratorInterface $parent = null,
        ?NodeInterface $current     = null,
        protected bool $includeSelf = false
    ) {
        if ($parent !== null) {
            $this->parent           = \WeakReference::create($parent);
        }

        if ($current !== null) {
            $this->childNodes       = [$current];
        }
    }

    #[\Override]
    public function getParentIterator(): \Iterator|null
    {
        return $this->parent?->get();
    }

    #[\Override]
    public function currentNode(): NodeInterface|null
    {
        return $this->current;
    }

    #[\Override]
    public function current(): mixed
    {
        return $this->current;
    }

    #[\Override]
    public function next(): void
    {
        if (($substitution = $this->current?->resolveSubstitution()) !== null
           && $substitution !== $this->current
           && $substitution->isNotTransformed()
        ) {
            $this->current          = $substitution;
            return;
        }

        do {

            ++$this->index;

            if ($this->childNodes === null || $this->index >= \count($this->childNodes)) {
                $this->current      = null;
                return;
            }

            $this->current          = $this->childNodes[$this->index];

            if ($this->current === null) {
                continue;
            }

            if ($this->current->isNotTransformed()) {
                return;
            }

        } while (true);
    }

    #[\Override]
    public function key(): mixed
    {
        return $this->index;
    }

    #[\Override]
    public function hasChildren(): bool
    {
        //
        // If the current node has a substitution, we don't need traversal through child nodes.
        //
        if (($substitution = $this->current?->resolveSubstitution()) !== null && $substitution !== $this->current) {
            return false;
        }

        return $this->current?->hasChildNodes() === true;
    }

    #[\Override]
    public function getChildren(): ?RecursiveIterator
    {
        if ($this->current === null) {
            return null;
        }

        return new self($this->current, $this);
    }

    #[\Override]
    public function valid(): bool
    {
        return $this->current !== null;
    }

    #[\Override]
    public function rewind(): void
    {
        $this->childNodes       = \array_values($this->node->getChildNodes());

        $this->current          = null;
        $this->index            = 0;

        if ($this->includeSelf) {
            $this->current      = $this->node;
        } elseif ($this->childNodes !== []) {
            // get the first node not null node
            while ($this->index < \count($this->childNodes)) {
                $this->current      = $this->childNodes[$this->index];

                if ($this->current !== null && $this->current->isNotTransformed()) {
                    break;
                }

                ++$this->index;
            }
        }
    }

    #[\Override]
    public function getPath(): array
    {
        $path                   = [];
        $current                = $this->current;
        $iterator               = $this;

        while ($current !== null && $iterator !== null) {
            $path[]             = $current;

            $iterator           = $iterator->getParent();
            $current            = $iterator?->currentNode();
        }

        return \array_reverse($path);
    }

    #[\Override]
    public function getParent(): ?self
    {
        return $this->parent?->get();
    }
}
