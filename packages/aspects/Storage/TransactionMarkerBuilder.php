<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Property\PropertyUuid;

class TransactionMarkerBuilder
{
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $namingStrategy                 = $entity->getNamingStrategy();
        $property                       = new PropertyUuid($namingStrategy->generatePropertyName([TransactionMarker::TRANSACTION]));
        $property->asInternal()->setTypicalName(TransactionMarker::TRANSACTION);

        $entity->describeProperty($property);
    }

}
