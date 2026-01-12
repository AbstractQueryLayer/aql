<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface QueryPostHandlerInterface
{
    /**
     * The method is called at the end of query normalization, when all normalization processes are completed.
     *
     *
     */
    public function postHandleQuery(BasicQueryInterface $query, NodeContextInterface $context): void;
}
