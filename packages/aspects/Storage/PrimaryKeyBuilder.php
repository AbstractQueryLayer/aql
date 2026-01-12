<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderAbstract;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyBigInteger;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyUlid;
use IfCastle\AQL\Entity\Property\PropertyUuid;
use IfCastle\Exceptions\UnexpectedValueType;

class PrimaryKeyBuilder extends EntityAspectBuilderAbstract
{
    #[\Override]
    public function applyAspect(AspectDescriptorInterface $aspectDescriptor, EntityDescriptorInterface & EntityInterface $entity): void
    {
        if ($aspectDescriptor instanceof PrimaryKey === false) {
            throw new UnexpectedValueType('$aspectDescriptor', $aspectDescriptor, PrimaryKey::class);
        }

        // Add ID to entity
        $this->describePrimaryKey($aspectDescriptor, $entity);
    }

    protected function describePrimaryKey(PrimaryKey $primaryKey, EntityDescriptorInterface & EntityInterface $entity): void
    {
        $type                       = $primaryKey->type;

        if ($type === '' && \array_key_exists(PrimaryKey::DEFAULT, $this->builderConfig)) {
            $type                   = $this->builderConfig[PrimaryKey::DEFAULT];
        }

        // By default, a type always BIG_INT
        $type                       = $type !== '' ? $type : PrimaryKey::BIG_INT;

        $property                   = match ($type) {
            PrimaryKey::INT         => (new PropertyInteger($primaryKey->name))->setIsAutoIncrement(true),
            PrimaryKey::BIG_INT     => (new PropertyBigInteger($primaryKey->name))->setIsAutoIncrement(true),
            PrimaryKey::UUID        => new PropertyUuid($primaryKey->name),
            PrimaryKey::ULID        => new PropertyUlid($primaryKey->name),
            default                 => throw new EntityDescriptorException([
                'template'  => 'Unknown primary key type {type} occurred',
                'type'      => $type,
            ]),
        };

        $property->setIsPrimaryKey(true);

        $entity->describeProperty($property)->describeKey((new Key($primaryKey->name))->asPrimary());
    }
}
