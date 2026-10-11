<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * Invalid on purpose: a reference column without an Id suffix and without an explicit role.
 */
class ArticleNote extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void
    {
        $this->describeReference(Article::entity(), propertyName: 'writtenFor');
    }
}
