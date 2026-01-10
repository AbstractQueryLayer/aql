<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\ActivationStatus;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderAbstract;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class ActivationStatusBuilder extends EntityAspectBuilderAbstract
{
    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        if ($aspectDescriptor instanceof ActivationStatus === false) {
            throw new UnexpectedValueType('$aspectDescriptor', $aspectDescriptor, ActivationStatus::class);
        }

        // Add ID to entity
        $this->describeActivationStatus($aspectDescriptor, $entity);
    }

    protected function describeActivationStatus(ActivationStatus $activationStatus, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $entity->describeProperty(new PropertyIsEnabled($activationStatus->propertyName));
    }
}
