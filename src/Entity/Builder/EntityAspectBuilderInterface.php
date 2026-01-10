<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface EntityAspectBuilderInterface
{
    public function setAspectBuilderConfig(array $config): static;

    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityInterface & EntityDescriptorInterface $entity): void;
}
