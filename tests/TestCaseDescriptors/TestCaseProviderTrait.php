<?php

declare(strict_types=1);

namespace IfCastle\AQL\TestCaseDescriptors;

trait TestCaseProviderTrait
{
    /**
     * Test case provider that unwinds methods and returns an array.
     *
     * @throws \ErrorException
     * @throws \Exception
     */
    public function caseProvider(): array
    {
        $class                      = new \ReflectionClass($this);
        $cases                      = [];
        $index                      = [];

        foreach ($class->getMethods(\ReflectionMethod::IS_PROTECTED) as $method) {

            $methodName             = $method->getName();

            if (!\str_starts_with($methodName, 'case')) {
                continue;
            }

            $testCases              = $this->$methodName();

            if ($testCases instanceof TestCaseDescriptorInterface === false && $testCases instanceof TestCaseSuiteInterface) {
                throw new \ErrorException(\sprintf('The method \'%s\' must return TestCaseDescriptorI or TestCaseSuiteI', $methodName));
            }

            $addTestCase            = static function (TestCaseDescriptorInterface $caseDescriptor) use (&$cases, &$index, $methodName) {
                $name               = $caseDescriptor->getTestCaseName();
                // Define default case name by method
                if ($name === '') {
                    $name           = \substr($methodName, 4);
                }
                if (\array_key_exists($name, $cases)) {

                    if (!\array_key_exists($name, $index)) {
                        $index[$name] = 0;
                    }

                    ++$index[$name];

                    $name           .= ': ' . $index[$name];
                }
                $cases[$name]       = [$caseDescriptor];
            };

            // One user story can generate several cases
            if ($testCases instanceof TestCaseSuiteInterface) {
                foreach ($testCases->getTestCaseDescriptors() as $caseDescriptor) {
                    $addTestCase($caseDescriptor);
                }
            } elseif ($testCases instanceof TestCaseDescriptorInterface) {
                $addTestCase($testCases);
            }
        }

        if ($cases === []) {
            throw new \Exception(
                'A class must define at least one method "protected function case{Name}(): TestCaseDescriptorI"'
            );
        }

        return $cases;
    }
}
