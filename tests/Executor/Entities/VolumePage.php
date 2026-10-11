<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/**
 * A page of one issue of a volume; the primary key is (issueId, volumeId, pageNo).
 */
class VolumePage extends EntityAbstract
{
    protected function buildAspects(): void {}

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('issueId'));
        $this->describeReference(Volume::entity());
        $this->describeProperty(new PropertyInteger('pageNo'));
        $this->describeProperty(new PropertyString('title'));
        $this->describeKey((new Key('issueId', 'volumeId', 'pageNo'))->asPrimary());
    }
}
