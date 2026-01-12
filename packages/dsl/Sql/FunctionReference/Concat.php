<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;

final class Concat extends FunctionReference
{
    public function __construct(NodeInterface ...$nodes)
    {
        parent::__construct('CONCAT', ...$nodes);
    }
}
