<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

/**
 * Class NodeList.
 *
 * @template T of NodeInterface
 */
final class NodeList extends NodeAbstract implements ChildNodeMutableInterface, \ArrayAccess
{
    private string|null $delimiter   = null;

    public function defineDelimiter(string $delimiter): static
    {
        $this->delimiter             = $delimiter;

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->childNodesToAql($this->delimiter ?? ', ');
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return \implode($this->delimiter ?? ', ', $this->generateResultForChildNodes());
    }

    #[\Override]
    public function shouldInheritContext(): bool
    {
        //
        // Node list usually does not have a context name,
        // so it should not inherit it from the parent node.
        //

        return true;
    }

    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->childNodes[]         = $node->setParentNode($this);
        }
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->childNodes === [];
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return $this->childNodes !== [];
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists((string) $offset, $this->childNodes);
    }

    /**
     *
     * @return T|null
     */
    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->childNodes[$offset] ?? null;
    }

    /**
     * @param T     $value
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (false === $value instanceof NodeInterface) {
            throw new \InvalidArgumentException('Value must be an instance of NodeInterface');
        }

        if ($offset === null) {
            $this->childNodes[]    = $value->setParentNode($this);
        } else {
            $this->childNodes[$offset] = $value->setParentNode($this);
        }

        $this->isTransformed       = false;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        if (\array_key_exists((string) $offset, $this->childNodes)) {
            unset($this->childNodes[$offset]);
            $this->isTransformed = false;
        }
    }
}
