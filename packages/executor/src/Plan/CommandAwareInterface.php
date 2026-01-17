<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface CommandAwareInterface
{
    public function getCommand(): CommandInterface;
}
