<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Parameter;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;

interface ParameterInterface extends ConstantInterface
{
    public function getParameterName(): string;

    public function isParameterDefault(): bool;

    public function isParameterResolved(): bool;

    public function setParameterValue(mixed $value): static;

    public function defineParameterSetter(callable $setter): static;

    public function referenceTo(ParameterInterface $parameter): static;
}
