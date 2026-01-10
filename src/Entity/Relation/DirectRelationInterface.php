<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;

interface DirectRelationInterface extends RelationInterface
{
    public function getLeftKey(): KeyInterface;

    public function getRightKey(): KeyInterface;
}
