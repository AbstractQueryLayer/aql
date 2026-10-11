<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * Two different edges to Novel: a many-to-many cross reference, and Novel.mainWriterId pointing back here.
 */
class Writer extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::NAME));
    }

    protected function buildProperties(): void
    {
        $this->describeCrossReference(Novel::entity());
    }
}
