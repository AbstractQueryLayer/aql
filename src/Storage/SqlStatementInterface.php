<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;

interface SqlStatementInterface
{
    public function getQuery(): string;

    public function getParameterDefinitions(): array;

    public function getParameterKeys(): array;

    public function bindParameter(ConstantInterface $parameter): void;
}
