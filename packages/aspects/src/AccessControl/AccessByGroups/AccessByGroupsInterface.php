<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\AccessControl\AccessByGroups;

interface AccessByGroupsInterface
{
    /**
     * Means property has default access.
     */
    public const string ACCESS_DEFAULT = '';

    /**
     * Means property has public access.
     */
    public const string ACCESS_PUBLIC = 'public';

    /**
     * Means only admins have access to this property.
     */
    public const string ACCESS_ADMIN = 'admin';

    /**
     * Only API code has access to property, no DTO.
     */
    public const string ACCESS_INTERNAL = 'internal';

    /**
     * API code should be only read this property, not write.
     */
    public const string ACCESS_READONLY = 'readonly';

    public function getAccessGroups(): array;

    public function hasAccess(string ...$accessGroups): bool;
}
