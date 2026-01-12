<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Transformer;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

class NodeTransformer
{
    public static function transformWhileNotResolved(NodeInterface $node, NodeContextInterface $context, callable $handler): void
    {
        while ($node->isNotTransformed()) {
            $node->transformWith($handler, $context);

            if (($substitution = $node->getSubstitution()) === null) {
                break;
            }

            $node                   = $substitution;
        }
    }
}
