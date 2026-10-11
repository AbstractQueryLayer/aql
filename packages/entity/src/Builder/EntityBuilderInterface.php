<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface EntityBuilderInterface
{
    public function buildEntity(EntityInterface & EntityDescriptorInterface $entity, bool $isRaw = false): void;

    /**
     * @param list<string> $derivedFrom
     */
    public function buildReference(
        EntityDescriptorInterface & EntityInterface          $fromEntity,
        string|(EntityInterface & EntityDescriptorInterface) $toEntity,
        string                                               $relationType = RelationInterface::REFERENCE,
        bool                                                 $isRequired = true,
        ?string                                               $propertyName = null,
        ?string                                               $role = null,
        string                                               $integrity = RelationInterface::INTEGRITY_DECLARED,
        array                                                $derivedFrom = []
    ): void;

    public function buildCrossReference(
        EntityDescriptorInterface & EntityInterface          $fromEntity,
        (EntityDescriptorInterface & EntityInterface)|string $toEntity,
        bool                                                 $isRequired = true,
        ?string                                               $throughEntity = null,
        string                                               $relationType = RelationInterface::REFERENCE
    ): void;

    public function buildInheritsEntity(
        EntityDescriptorInterface & EntityInterface $entity,
        string                                      $inheritedEntity
    ): void;
}
