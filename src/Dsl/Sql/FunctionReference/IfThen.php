<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;

final class IfThen extends FunctionReference
{
    public function __construct(NodeInterface $condition, NodeInterface $then, NodeInterface $else)
    {
        parent::__construct('IF', $condition, $then, $else);
    }
}
