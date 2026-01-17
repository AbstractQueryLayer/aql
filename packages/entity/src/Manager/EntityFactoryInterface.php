<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Manager;

use IfCastle\AQL\Entity\EntityInterface;

interface EntityFactoryInterface
{
    final public const string TYPICAL_PREFIX = '@';

    public function getEntity(string $entityName, bool $isRaw = false): EntityInterface;

    public function findEntity(string $entityName, bool $isRaw = false): ?EntityInterface;

    public function findTypicalEntity(string $entityName, bool $isRaw = false): ?EntityInterface;

    public function findEntityClass(string $entityName): ?string;
}
