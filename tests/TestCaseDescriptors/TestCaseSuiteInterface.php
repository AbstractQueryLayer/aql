<?php

declare(strict_types=1);

namespace IfCastle\AQL\TestCaseDescriptors;

interface TestCaseSuiteInterface
{
    public function getTestCaseSuitName(): string;

    /**
     * @return TestCaseDescriptorInterface[]
     */
    public function getTestCaseDescriptors(): array;
}
