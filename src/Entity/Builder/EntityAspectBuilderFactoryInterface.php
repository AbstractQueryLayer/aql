<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AOP\AspectDescriptorInterface;

interface EntityAspectBuilderFactoryInterface
{
    public function getEntityAspectBuilder(AspectDescriptorInterface $aspectDescriptor): EntityAspectBuilderInterface;
}
