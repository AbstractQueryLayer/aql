<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyDateTime;
use IfCastle\AQL\Entity\Property\PropertyInteger;

/** Time columns and a nullable counter for the MariaDB UTC_TIMESTAMP, INTERVAL, IS NULL and COALESCE tests. */
class AqlRoundTripLease extends EntityAbstract
{
    #[\Override]
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey(PrimaryKey::INT));
    }

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('ownerId'))
            ->describeProperty(new PropertyDateTime('expiresAt'))
            ->describeProperty(new PropertyDateTime('windowStart'))
            ->describeProperty(new PropertyInteger('attempts'))
            ->describeProperty(new PropertyInteger('bonus', null, true));
    }
}
