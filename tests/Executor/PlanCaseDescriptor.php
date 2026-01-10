<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\TestCaseDescriptors\TestCaseDescriptorInterface;

class PlanCaseDescriptor implements TestCaseDescriptorInterface
{
    public array $storedResult = [];

    public array|int|float|null $expectedResult = null;

    public function __construct(public string $aql, public ?string $name = null) {}

    #[\Override]
    public function getTestCaseName(): string
    {
        return $this->name ?? $this->aql;
    }

    public function addStoredResult(string $sql, array|int|float|null $result, ?string $storage = null): self
    {
        $sql                        = \trim((string) \preg_replace(['/\s+/'], ' ', $sql));
        $this->storedResult[$sql]   = [$result, $storage];
        return $this;
    }
}
