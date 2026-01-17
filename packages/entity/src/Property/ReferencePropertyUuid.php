<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Relation\RelationInterface;

class ReferencePropertyUuid extends PropertyUuid
{
    /**
     * ReferenceProperty constructor.
     * @param   string              $name           Name of property
     * @param   string              $entityName     Entity name
     * @param   string|null         $relationType   Relations type
     */
    public function __construct(string $name, string $entityName, ?string $relationType = null, bool $isNullable = false)
    {
        parent::__construct($name);
        $this->referenceToEntity    = $entityName;
        $this->relationType         = $relationType ?? RelationInterface::REFERENCE;
        $this->isNotNull            = !$isNullable;
    }
}
