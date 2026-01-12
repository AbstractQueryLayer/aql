<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;

class UnknownQueryNode extends QueryException
{
    protected string $template      = 'Unknown query node {node} ({nodeClass}). Query: {query}';

    public function __construct(BasicQueryInterface $query, NodeInterface $node)
    {
        parent::__construct([
            'query'                 => $query,
            'node'                  => $node->getNodeName(),
            'nodeClass'             => $node::class,
        ]);
    }
}
