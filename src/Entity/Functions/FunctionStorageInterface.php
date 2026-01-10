<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Functions;

use IfCastle\AQL\Executor\Exceptions\FunctionNotFound;

interface FunctionStorageInterface
{
    /**
     * @throws FunctionNotFound
     */
    public function getFunction(string $functionName): FunctionInterface;

    public function findFunction(string $functionName): ?FunctionInterface;

    public function hasFunction(string $functionName): bool;

    public function addFunction(FunctionInterface $function, bool $isRedefine = false): static;
}
