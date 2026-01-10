<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\PostAction;

use IfCastle\AQL\Executor\Plan\Command;
use IfCastle\AQL\Executor\Plan\ExecutionPlanInterface;

class PostActionCommand extends Command
{
    public function __construct(PostActionInterface $postAction)
    {
        parent::__construct(ExecutionPlanInterface::POST_ACTION, $postAction);
    }
}
