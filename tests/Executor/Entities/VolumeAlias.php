<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * Another name of a volume: its role reaches Volume by the unique key on Volume.name, not by the primary key.
 */
class VolumeAlias extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('volumeName'));
    }

    protected function buildRelations(): void
    {
        $this->describeRole('volume', Volume::entity(), ['volumeName' => 'name'], RelationInterface::INTEGRITY_LOGICAL);
    }
}
