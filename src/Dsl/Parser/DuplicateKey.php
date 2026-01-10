<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\DuplicateKey as DuplicateKeyNode;

class DuplicateKey extends AssignmentList
{
    #[\Override]
    protected function createNode(NodeInterface ...$expressions): DuplicateKeyNode
    {
        return new DuplicateKeyNode(...$expressions);
    }
}
