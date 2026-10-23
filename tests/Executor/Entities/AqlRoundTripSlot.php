<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/** Ordered slots of a round-trip row; the primary key is (roundTripId, position) and position starts at 0. */
class AqlRoundTripSlot extends EntityAbstract
{
    #[\Override]
    protected function buildAspects(): void {}

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('roundTripId'));
        $this->describeProperty(new PropertyInteger('position'));
        $this->describeProperty(new PropertyString('label', maxLength: 64));
        $this->describeKey((new Key('roundTripId', 'position'))->asPrimary());
    }
}
