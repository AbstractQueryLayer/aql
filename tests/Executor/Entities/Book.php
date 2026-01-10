<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Aspects\Storage\Timestamps;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Property\PropertyBoolean;
use IfCastle\AQL\Entity\Property\PropertyDateTime;
use IfCastle\AQL\Entity\Property\PropertyFloat;

/**
 * Example of simple entity.
 *
 */
class Book extends EntityAbstract
{
    public static function getBaseDir(): string
    {
        return __DIR__;
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
            ->describeAspect(new Description(Description::TITLE))
            ->describeAspect(new Timestamps(Timestamps::CREATED, Timestamps::UPDATED));
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function buildProperties(): void
    {
        /**
         * One book can relate to one Book section. This relation is required.
         */
        $this->describeReference(BookSection::entity());
        /**
         * One book can relate to one Book provider. Not required!
         */
        $this->describeReference(BookProvider::entity(), RelationInterface::REFERENCE, false);

        /**
         * One book can relate to many authors. This relation is required.
         */
        $this->describeCrossReference(Author::entity());

        $this
            ->describeProperty(new PropertyBoolean('isTop'))
            ->describeProperty(new PropertyFloat('price'))
            ->describeProperty(new PropertyDateTime('publishedDate'));
    }
}
