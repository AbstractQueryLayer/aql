<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

class QueryCommandContext extends ExecutionContext
{
    public function __construct(?ExecutionContextInterface $parent = null)
    {
        parent::__construct(parent: $parent);
    }
}
