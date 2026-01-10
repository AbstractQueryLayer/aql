<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\TestCaseDescriptors\TestCaseDescriptorInterface;

final class SqlQueryCaseDescriptor implements TestCaseDescriptorInterface
{
    public function __construct(public string $aql, public ?string $name = null) {}

    #[\Override]
    public function getTestCaseName(): string
    {
        return $this->name ?? $this->aql;
    }
}
