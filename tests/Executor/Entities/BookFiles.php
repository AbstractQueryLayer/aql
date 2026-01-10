<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities;

use IfCastle\AQL\Aspects\Descriptions\Description;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Aspects\Storage\Timestamps;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Storage\SomeStorageMock;

/**
 * BookFiles entity.
 * An entity that stores files in a separate database and is related to books with a BELONGS_TO relationship.
 * In other words, one book can have many files, and many files can belong to one book.
 */
class BookFiles extends EntityAbstract
{
    protected ?string $storageName      = SomeStorageMock::NAME;

    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey())
             ->describeAspect(new Description(Description::TITLE))
             ->describeAspect(new Timestamps(Timestamps::CREATED, Timestamps::UPDATED));
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('filename'));
        $this->describeReference(Book::entity(), RelationInterface::BELONGS_TO);
    }
}
