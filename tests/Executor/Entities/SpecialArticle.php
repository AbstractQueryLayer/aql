<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * Inherits Article, and with it two roles to one entity.
 */
class SpecialArticle extends EntityAbstract
{
    protected function defineInheritedEntity(): ?string
    {
        return Article::entity();
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
