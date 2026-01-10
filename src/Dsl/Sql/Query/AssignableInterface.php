<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;

interface AssignableInterface
{
    public function assigns(Assign ...$assigns): static;

    public function assign(string $column, int|bool|float|string|null $value): static;

    public function assignKeyValues(array $keyValues): static;
}
