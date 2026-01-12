<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;

final class ConcatWs extends FunctionReference
{
    public function __construct(string $separator, NodeInterface ...$nodes)
    {
        parent::__construct('CONCAT_WS', new Variable($separator), ...$nodes);
    }
}
