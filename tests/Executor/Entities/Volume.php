<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * A volume pointing at its published issue: the shape of works and edition_chapters in the lib schema. Its
 * composite roles reach the pages of that issue (to-many) and its first page (to-one, the page's primary key). The
 * name is unique, for VolumeAlias.
 */
class Volume extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('name'));
        $this->describeProperty(new PropertyInteger('publishedIssueId', isNullable: true));
        $this->describeProperty(new PropertyInteger('firstPageNo', isNullable: true));
        $this->describeKey((new Key('name'))->asUnique());
    }

    protected function buildRelations(): void
    {
        $this->describeRole(
            'publishedPages',
            VolumePage::entity(),
            ['publishedIssueId' => 'issueId', 'id' => 'volumeId'],
            RelationInterface::INTEGRITY_IMPLIED,
            ['fk_volume_published', 'fk_volume_page_issue'],
        );
        $this->describeRole(
            'firstPage',
            VolumePage::entity(),
            ['publishedIssueId' => 'issueId', 'id' => 'volumeId', 'firstPageNo' => 'pageNo'],
            RelationInterface::INTEGRITY_LOGICAL,
        );
    }
}
