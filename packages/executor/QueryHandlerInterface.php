<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

/**
 * An interface for a request handler of type Visitor that handles known nodes:
 * - query
 * - option
 * - subject
 * - join
 * - column
 * - function
 */
interface QueryHandlerInterface
{
    /**
     * Processing the Query node.
     *
     *
     */
    public function handleQuery(BasicQueryInterface $query, NodeContextInterface $context): void;
}
