<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\AccessControl\AccessByGroups;

interface AccessByGroupsInterface
{
    /**
     * Means property has default access.
     * @var string
     */
    public const string ACCESS_DEFAULT = '';

    /**
     * Means property has public access.
     * @var string
     */
    public const string ACCESS_PUBLIC = 'public';

    /**
     * Means only admins have access to this property.
     * @var string
     */
    public const string ACCESS_ADMIN = 'admin';

    /**
     * Only API code has access to property, no DTO.
     * @var string
     */
    public const string ACCESS_INTERNAL = 'internal';

    /**
     * API code should be only read this property, not write.
     * @var string
     */
    public const string ACCESS_READONLY = 'readonly';

    public function getAccessGroups(): array;

    public function hasAccess(string ...$accessGroups): bool;
}
