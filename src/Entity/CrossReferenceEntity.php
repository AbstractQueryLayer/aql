<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AQL\Dsl\Relation\RelationInterface;

final class CrossReferenceEntity extends EntityAbstract
{
    protected bool $isPrimaryKeyRequired    = false;

    private string $fromEntity;

    private string $toEntity;

    private string $relationType;

    private bool $isRelationRequired        = false;

    public static function instantiate(
        string $entityName,
        string $fromEntity,
        string $toEntity,
        string $relationType                = RelationInterface::REFERENCE,
        bool $isRequired                    = false
    ): self {
        $crossReferenceEntity               = new self();

        $crossReferenceEntity->name         = $entityName;
        $crossReferenceEntity->fromEntity   = $fromEntity;
        $crossReferenceEntity->toEntity     = $toEntity;
        $crossReferenceEntity->relationType = $relationType;
        $crossReferenceEntity->isRelationRequired = $isRequired;

        return $crossReferenceEntity;
    }

    #[\Override]
    protected function buildAspects(): void {}

    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeReference($this->toEntity, $this->relationType, $this->isRelationRequired);
        $this->describeReference($this->fromEntity, $this->relationType, $this->isRelationRequired);
    }
}
