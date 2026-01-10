<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;

class InvalidQueryNode extends QueryException
{
    protected string $template      = 'Invalid query node {node} ({nodeClass}). Reason: {reason}. Query: {query}';

    public function __construct(BasicQueryInterface $query, NodeInterface $node, string $reason = '')
    {
        parent::__construct([
            'query'                 => $query,
            'node'                  => $node->getNodeName(),
            'nodeClass'             => $node::class,
            'reason'                => $reason,
        ]);
    }
}
