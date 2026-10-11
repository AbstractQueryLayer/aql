<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * Inherits Volume, and with it the composite roles of Volume.
 */
class SpecialVolume extends EntityAbstract
{
    protected function defineInheritedEntity(): ?string
    {
        return Volume::entity();
    }

    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('badge'));
    }
}
