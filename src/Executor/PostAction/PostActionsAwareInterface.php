<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\PostAction;

interface PostActionsAwareInterface
{
    public function getPostActions(): array;
}
