<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface OperationInterface extends NodeInterface
{
    public function getOperation(): string;
}
