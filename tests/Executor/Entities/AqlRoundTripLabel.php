<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/** Labels of a round-trip row; the primary key is (roundTripId, label) and there is no PrimaryKey aspect. */
class AqlRoundTripLabel extends EntityAbstract
{
    #[\Override]
    protected function buildAspects(): void {}

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('roundTripId'));
        $this->describeProperty(new PropertyString('label', maxLength: 64));
        $this->describeKey((new Key('roundTripId', 'label'))->asPrimary());
    }
}
