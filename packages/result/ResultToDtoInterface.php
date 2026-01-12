<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

use IfCastle\TypeDefinitions\Value\ValueContainerInterface;

interface ResultToDtoInterface
{
    public function resultToDto(ResultInterface $result): ValueContainerInterface;
}
