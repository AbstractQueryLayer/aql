<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface QueryOptionsInterface extends NodeInterface
{
    public function findOption(string $optionName): ?QueryOptionInterface;

    public function getOption(string $optionName): QueryOptionInterface;

    public function isOption(string $optionName): bool;

    public function addOption(QueryOptionInterface|string $option, bool $isUnique = true, bool $isRedefine = true): static;

    public function removeOption(string $optionName): static;
}
