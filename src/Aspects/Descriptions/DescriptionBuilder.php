<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Descriptions;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderAbstract;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Entity\Property\PropertyText;
use IfCastle\Exceptions\UnexpectedValueType;

class DescriptionBuilder extends EntityAspectBuilderAbstract
{
    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        if ($aspectDescriptor instanceof Description === false) {
            throw new UnexpectedValueType('$aspectDescriptor', $aspectDescriptor, Description::class);
        }

        // Add ID to entity
        $this->describeDescription($aspectDescriptor, $entity);
    }

    protected function describeDescription(Description $description, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $property                   = match ($description->property) {
            Description::NAME, Description::TITLE
            => (new PropertyString($description->property))->setTypicalName($description->property),
            Description::DESCRIPTION => (new PropertyText($description->property))->setTypicalName($description->property),

            default                 => throw new EntityDescriptorException([
                'template'          => 'Unknown description property {property} occurred',
                'property'          => $description->property,
            ]),
        };

        $entity->describeProperty($property);
    }
}
