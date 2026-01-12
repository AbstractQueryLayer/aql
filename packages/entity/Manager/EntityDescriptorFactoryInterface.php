<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Manager;

use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface EntityDescriptorFactoryInterface extends EntityFactoryInterface
{
    public function getEntityDescriptor(string $entityName, bool $isRaw = false): EntityInterface & EntityDescriptorInterface;
}
