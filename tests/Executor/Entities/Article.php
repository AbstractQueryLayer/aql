<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;

/**
 * An article with two roles to one entity: its draft and its published revision.
 */
class Article extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::NAME));
    }

    protected function buildProperties(): void
    {
        $this->describeReference(
            ArticleRevision::entity(), isRequired: false, propertyName: 'draftRevisionId'
        );
        $this->describeReference(
            ArticleRevision::entity(),
            isRequired: false,
            propertyName: 'publishedRevisionId',
            integrity: RelationInterface::INTEGRITY_IMPLIED,
            derivedFrom: ['fk_article_published', 'fk_edition_revision'],
        );
    }
}
