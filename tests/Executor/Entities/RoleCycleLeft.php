<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * Has a composite role to RoleCycleRight, which has one back: building either needs the other fully built.
 */
class RoleCycleLeft extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void {}

    protected function buildRelations(): void
    {
        $this->describeRole('right', RoleCycleRight::entity(), ['id' => 'id'], RelationInterface::INTEGRITY_LOGICAL);
    }
}
