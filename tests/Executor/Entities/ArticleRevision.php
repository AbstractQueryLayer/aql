<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * A revision of an article; `parent` is a role of the entity to itself.
 */
class ArticleRevision extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::TITLE));
    }

    protected function buildProperties(): void
    {
        $this->describeReference(Article::entity());
        $this->describeReference(self::entity(), isRequired: false, propertyName: 'parentId');
    }
}
