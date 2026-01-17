<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use RecursiveIterator;

class NodeRecursiveIterator extends NodeIterator implements \RecursiveIterator
{
    #[\Override]
    public function hasChildren(): bool
    {
        return $this->current->hasChildNodes();
    }

    #[\Override]
    public function getChildren(): ?RecursiveIterator
    {
        return new self($this->current, $this->resolveSubstitution, $this->savePath);
    }
}
