<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Manager;

use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface EntityStorageInterface extends EntityFactoryInterface
{
    public function setEntity(EntityInterface & EntityDescriptorInterface $entity): static;

    public function newEntity(string $entityName): EntityInterface & EntityDescriptorInterface;
}
