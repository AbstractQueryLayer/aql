<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\AccessControl\AccessByGroups;

interface AccessByGroupsMutableInterface extends AccessByGroupsInterface
{
    public function setAccessGroups(string ...$accessGroups): static;
}
