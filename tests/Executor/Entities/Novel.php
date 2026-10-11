<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;

class Novel extends EntityAbstract
{
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::TITLE));
    }

    protected function buildProperties(): void
    {
        $this->describeReference(Writer::entity(), isRequired: false, propertyName: 'mainWriterId');
    }
}
