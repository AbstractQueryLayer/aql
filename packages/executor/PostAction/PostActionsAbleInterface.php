<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\PostAction;

interface PostActionsAbleInterface extends PostActionsAwareInterface
{
    public function addPostAction(PostActionInterface $postAction): void;
}
