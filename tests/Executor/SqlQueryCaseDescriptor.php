<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\TestCaseDescriptors\TestCaseDescriptorInterface;

class SqlQueryCaseDescriptor implements TestCaseDescriptorInterface
{
    public function __construct(public string $aql, public string $sql, public ?string $name = null) {}

    #[\Override]
    public function getTestCaseName(): string
    {
        return $this->name ?? $this->aql;
    }
}
