<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyInteger;

/** Rows of an owner ordered by creation time, for the MariaDB DELETE ... ORDER BY ... LIMIT tests. */
class AqlRoundTripEvent extends EntityAbstract
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
            ->describeProperty(new PropertyInteger('createdAt'));
    }
}
