<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Aspects\Storage\Timestamps;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Executor\Entities\Properties\EncodedProperty;

class Author extends EntityAbstract
{
    /**
     * @throws EntityDescriptorException
     */
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::NAME))
            ->describeAspect(new Timestamps(Timestamps::CREATED, Timestamps::UPDATED));
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function buildProperties(): void
    {
        $this->describeProperty(new EncodedProperty());
    }
}
