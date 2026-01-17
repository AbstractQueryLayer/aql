<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Relation\RelationInterface;

interface IndirectRelationInterface extends RelationInterface
{
    /**
     * @return string[]
     */
    public function getEntitiesPath(): array;
}
