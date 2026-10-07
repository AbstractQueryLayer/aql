<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/** A separate entity with no AQL relation to AqlRoundTrip. */
class AqlRoundTripLink extends EntityAbstract
{
    #[\Override]
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey(PrimaryKey::INT));
    }

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('roundTripId'))
            ->describeProperty(new PropertyString('label', maxLength: 64));
    }
}
