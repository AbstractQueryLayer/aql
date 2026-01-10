<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Aspects\Storage\Timestamps;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * An example of an entity that inherits an entity:
 * - Magazine inherits from Book
 */
class Magazine extends EntityAbstract
{
    protected function defineInheritedEntity(): ?string
    {
        return Book::entity();
    }

    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Timestamps(Timestamps::CREATED, Timestamps::UPDATED));
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('subscription'));
    }
}
