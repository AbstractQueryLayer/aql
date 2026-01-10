<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Ddl;

interface DdlExecutorFactoryInterface
{
    public function newDdlExecutor(string $entityName): DdlExecutorInterface;
}
