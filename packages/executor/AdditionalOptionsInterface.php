<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Transaction\WithTransactionInterface;

interface AdditionalOptionsInterface extends WithTransactionInterface
{
    public function getAdditionalOptions(): array;

    public function setOption(string $name, mixed $value): void;

    public function getOption(string $name): mixed;
}
