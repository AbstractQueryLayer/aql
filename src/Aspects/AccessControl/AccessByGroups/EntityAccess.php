<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\AccessControl\AccessByGroups;

use Attribute;
use IfCastle\AOP\AspectBuilderClassAwareInterface;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class EntityAccess implements AspectDescriptorInterface, AspectBuilderClassAwareInterface
{
    final public const string NAME             = 'AccessControl\\EntityAccess\\EntityAccess';

    /**
     * Means entity has public access.
     * @var string
     */
    final public const string ACCESS_PUBLIC    = 'public';

    /**
     * Means only admins have access to this entity.
     * @var string
     */
    final public const string ACCESS_ADMIN     = 'admin';

    /**
     * Only API code has access to entity, no DTO.
     * @var string
     */
    final public const string ACCESS_INTERNAL  = 'internal';

    /**
     * API code should be only read this entity, not write.
     * @var string
     */
    final public const string ACCESS_READONLY  = 'readonly';

    public function __construct(public array $accessGroups = []) {}

    public function isAllowed(string $currentScope): bool
    {
        return \in_array($currentScope, $this->accessGroups);
    }

    public function isActionAllowed(string $currentScope, string $action): bool
    {
        return true;
    }

    #[\Override]
    public function getAspectName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return [];
    }

    #[\Override]
    public function getAspectBuilderClass(): string
    {
        return EntityAspectBuilderNull::class;
    }
}
