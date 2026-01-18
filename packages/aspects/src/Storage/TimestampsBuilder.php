<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderAbstract;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Property\PropertyTimestamp;
use IfCastle\Exceptions\UnexpectedValue;

class TimestampsBuilder extends EntityAspectBuilderAbstract
{
    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $namingStrategy                 = $entity->getNamingStrategy();

        foreach ($aspectDescriptor->getAspectOptions() as $option) {

            $words                          = \explode('_', (string) $option);

            $property = match ($option) {
                Timestamps::CREATED => new PropertyTimestamp($namingStrategy->generatePropertyName($words))->makeAsCreatedAt(),
                Timestamps::UPDATED => new PropertyTimestamp($namingStrategy->generatePropertyName($words))->makeAsUpdatedAt(),
                Timestamps::PUBLISHED, Timestamps::DELETED => new PropertyTimestamp($namingStrategy->generatePropertyName($words), true),
                default => throw new UnexpectedValue('AspectOption', $option, 'expected CREATED, UPDATED'),
            };

            $property->asInternal()->setTypicalName($option);

            $entity->describeProperty($property);
        }
    }
}
