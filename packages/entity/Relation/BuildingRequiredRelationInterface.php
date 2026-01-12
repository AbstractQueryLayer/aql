<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;

/**
 * Relationships that require an additional building step before they can be used.
 *
 */
interface BuildingRequiredRelationInterface
{
    public function buildRelations(EntityFactoryInterface $entitiesFactory): RelationInterface;
}
