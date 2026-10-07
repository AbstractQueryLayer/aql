<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/** A small real-table descriptor for the MariaDB AQL integration tests. */
class AqlRoundTrip extends EntityAbstract
{
    #[\Override]
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey(PrimaryKey::INT));
    }

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('value', maxLength: 64));
    }
}
