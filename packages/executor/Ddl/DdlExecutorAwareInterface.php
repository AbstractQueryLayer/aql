<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Ddl;

interface DdlExecutorAwareInterface
{
    public function getDdlExecutor(): DdlExecutorInterface;
}
