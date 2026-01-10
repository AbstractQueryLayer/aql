<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\DesignPatterns\Iterators\IteratorWithPathInterface;

/**
 * ## NodeIterator with substitutions
 * provides traversal through child nodes.
 *
 */
class NodeIterator implements \Iterator, IteratorWithPathInterface
{
    protected array $path           = [];

    protected ?array $childNodes    = null;

    protected ?NodeInterface $current = null;

    protected int $index            = 0;

    public function __construct(protected NodeInterface $node, protected bool $resolveSubstitution = false, protected bool $savePath = false) {}

    #[\Override]
    public function getPath(): array
    {
        return $this->path;
    }

    #[\Override]
    public function current(): mixed
    {
        return $this->current;
    }

    #[\Override]
    public function next(): void
    {
        ++$this->index;

        if ($this->index >= \count($this->childNodes ?? [])) {
            $this->current          = null;
            return;
        }

        $this->current              = $this->childNodes[$this->index];
        $this->path[]               = [];

        if ($this->savePath && $this->resolveSubstitution) {

            while ($this->current?->getSubstitution() !== null) {
                $this->path[]       = $this->current;
                $this->current      = $this->current->getSubstitution();
            }
        } elseif ($this->resolveSubstitution) {
            $this->current              = $this->current?->resolveSubstitution();
        }
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
        $this->childNodes       = \array_values(\array_filter($this->node->getChildNodes(), static fn($node) => $node !== null));
        $this->current          = null;
        $this->index            = 0;

        if ($this->childNodes !== []) {
            $this->current      = $this->childNodes[0];
        }
    }
}
