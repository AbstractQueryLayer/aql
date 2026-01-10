<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Aspects\Storage\Timestamps;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;

class BookSection extends EntityAbstract
{
    /**
     * @throws EntityDescriptorException
     */
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::TITLE, Description::DESCRIPTION))
            ->describeAspect(new Timestamps(Timestamps::PUBLISHED, Timestamps::CREATED, Timestamps::UPDATED));
    }

    protected function buildProperties(): void {}
}
