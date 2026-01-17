<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node\Exceptions;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\Exceptions\BaseException;

class NodeException extends BaseException
{
    public function __construct(NodeInterface|array $node, string $message = '')
    {
        if (\is_array($node)) {
            parent::__construct($node);
        } else {
            parent::__construct([
                'message'           => $message,
                'node'              => $node::class,
                'aql'               => $node->getAql(),
            ]);
        }
    }
}
