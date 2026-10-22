<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * AqlRoundTripNote kept in the main storage: the same query on it runs as one SQL statement, so a test compares a
 * plan across storages with it.
 */
class AqlRoundTripLocalNote extends EntityAbstract
{
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
