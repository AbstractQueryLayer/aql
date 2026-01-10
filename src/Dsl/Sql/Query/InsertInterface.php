<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

interface InsertInterface extends QueryInterface, AssignableInterface
{
    public function markAsReplace(): static;
}
