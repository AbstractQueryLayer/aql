<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface RelationInheritByInterface
{
    public function relationInheritBy(EntityInterface $parentEntity, EntityInterface $childEntity): ?RelationInterface;
}
