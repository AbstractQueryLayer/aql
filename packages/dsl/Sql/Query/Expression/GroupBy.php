<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

class GroupBy extends NodeAbstract implements GroupByInterface
{
    protected string $nodeName      = QueryInterface::NODE_GROUP_BY;

    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->addGroupBy($node);
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
    public function addGroupBy(NodeInterface $node): static
    {
        $this->childNodes[]         = $node->setParentNode($this);
        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = $this->childNodesToAql(', ');

        if ($aql === '') {
            return '';
        }

        return 'GROUP BY ' . $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return 'GROUP BY ' . \implode(', ', $result);
    }
}
