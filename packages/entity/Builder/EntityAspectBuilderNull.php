<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;

/**
 * Null builder for aspects.
 */
final class EntityAspectBuilderNull implements EntityAspectBuilderInterface
{
    #[\Override]
    public function setAspectBuilderConfig(array $config): static
    {
        return $this;
    }

    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityInterface & EntityDescriptorInterface $entity): void {}
}
