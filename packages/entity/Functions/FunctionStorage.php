<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Functions;

use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Executor\Exceptions\FunctionNotFound;

class FunctionStorage implements FunctionStorageInterface
{
    /**
     * @var FunctionInterface[]
     */
    protected array $functions = [];

    /**
     * @throws FunctionNotFound
     */
    #[\Override]
    public function getFunction(string $functionName): FunctionInterface
    {
        return $this->functions[$functionName] ?? throw new FunctionNotFound($functionName);
    }

    #[\Override]
    public function findFunction(string $functionName): ?FunctionInterface
    {
        return $this->functions[$functionName] ?? null;
    }

    #[\Override]
    public function hasFunction(string $functionName): bool
    {
        return \array_key_exists($functionName, $this->functions);
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function addFunction(FunctionInterface $function, bool $isRedefine = false): static
    {
        if (\array_key_exists($function->getFunctionName(), $this->functions) && $isRedefine === false) {
            throw new EntityDescriptorException([
                'template'          => 'Try to redefine function {function}',
                'function'          => $function->getFunctionName(),
            ]);
        }

        $this->functions[$function->getFunctionName()] = $function;
        return $this;
    }
}
