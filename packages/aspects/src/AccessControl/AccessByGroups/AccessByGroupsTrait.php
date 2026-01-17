<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\AccessControl\AccessByGroups;

trait AccessByGroupsTrait
{
    /**
     * Access groups (default group by default).
     * @var string[]
     */
    protected array $accessGroups   = [AccessByGroupsInterface::ACCESS_DEFAULT];

    /**
     * @return string[]
     */
    public function getAccessGroups(): array
    {
        return $this->accessGroups;
    }

    public function hasAccess(string ...$accessGroups): bool
    {
        foreach ($accessGroups as $group) {
            if (\in_array($group, $this->accessGroups)) {
                return true;
            }
        }

        return false;
    }
}
