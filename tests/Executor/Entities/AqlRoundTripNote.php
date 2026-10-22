<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * A note on an AqlRoundTrip kept in another storage: a join to AqlRoundTrip runs as a second query of the plan.
 */
class AqlRoundTripNote extends EntityAbstract
{
    public const string STORAGE     = 'aqlRemote';

    protected ?string $storageName  = self::STORAGE;

    #[\Override]
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey(PrimaryKey::INT));
    }

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('label', maxLength: 64))
            ->describeReference(AqlRoundTrip::entity(), RelationInterface::BELONGS_TO, propertyName: 'roundTripId');
    }
}
