<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Publicity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderAbstract;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Property\PropertyBoolean;
use IfCastle\Exceptions\UnexpectedValueType;

class PublicityBuilder extends EntityAspectBuilderAbstract
{
    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        if ($aspectDescriptor instanceof Publicity === false) {
            throw new UnexpectedValueType('$aspectDescriptor', $aspectDescriptor, Publicity::class);
        }

        // Add ID to entity
        $this->describePublicity($aspectDescriptor, $entity);
    }

    protected function describePublicity(Publicity $description, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $entity->describeProperty(
            new PropertyBoolean($description->property)->setTypicalName(Publicity::TYPICAL_IS_PUBLISHED)
        );
    }
}
