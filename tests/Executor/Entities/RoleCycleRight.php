<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * The other end of RoleCycleLeft.
 */
class RoleCycleRight extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void {}

    protected function buildRelations(): void
    {
        $this->describeRole('left', RoleCycleLeft::entity(), ['id' => 'id'], RelationInterface::INTEGRITY_LOGICAL);
    }
}
