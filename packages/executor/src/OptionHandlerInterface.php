<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\QueryOptionInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface OptionHandlerInterface
{
    public function handleOption(QueryOptionInterface $queryOption, NodeContextInterface $context): void;
}
