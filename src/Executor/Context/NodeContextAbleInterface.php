<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

interface NodeContextAbleInterface
{
    public function getNodeContext(): NodeContextInterface|null;

    public function setNodeContext(NodeContextInterface $context): void;
}
