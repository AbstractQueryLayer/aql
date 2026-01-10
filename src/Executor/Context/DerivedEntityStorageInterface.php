<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

use IfCastle\AQL\Entity\DerivedEntity\DerivedEntityInterface;

interface DerivedEntityStorageInterface
{
    public function addDerivedEntity(DerivedEntityInterface $entity): static;

    public function findDerivedEntityStorage(): DerivedEntityStorageInterface|null;

    public function asDerivedEntityStorage(): static;

    public function findDerivedEntity(string $entityName, bool $partialDefinition = false): DerivedEntityInterface|null;

    public function getListOfDerivedEntities(): array;
}
