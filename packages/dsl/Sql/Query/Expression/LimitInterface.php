<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface LimitInterface extends NodeInterface
{
    public function isNotEmpty(): bool;

    public function getOffset(): int;

    public function getLimit(): int;

    public function setOffset(int $offset): static;

    public function setLimit(int $limit): static;
}
