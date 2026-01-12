<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AQL\Entity\Property\PropertyInterface;

interface PropertyResolverInterface
{
    public function resolveAllProperties(): array;

    public function resolveProperty(string $propertyName, ?string $entityName = null): PropertyInterface|null;
}
