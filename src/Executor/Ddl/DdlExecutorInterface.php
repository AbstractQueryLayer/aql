<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Ddl;

interface DdlExecutorInterface
{
    public function isRemoveExisted(): bool;

    public function asRemoveExisted(): static;

    public function executeDdl(): bool;
}
