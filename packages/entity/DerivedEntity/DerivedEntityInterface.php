<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AQL\Entity\EntityInterface;

interface DerivedEntityInterface extends EntityInterface
{
    public function getOriginalEntity(): EntityInterface;

    public function setResolvePropertiesMode(bool $shouldResolveProperties): static;
}
