<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder\NamingStrategy;

use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\TypeDefinitions\Value\ValueString;

/**
 * Strategy for naming tables in snake case and columns in camel case.
 */
final class SnakeTableCamelFieldNaming extends CamelCaseNaming
{
    #[\Override]
    public function generateTableName(array|EntityInterface $entity): string
    {
        return $entity instanceof EntityInterface ? ValueString::camelToSnake($entity->getEntityName()) : SnakeCaseNaming::toSnakeCase(...$entity);
    }

    #[\Override]
    public function generateConstraintName(string|EntityInterface $fromEntity, string|EntityInterface $toEntity, string $key): string
    {
        $toEntity                   = $toEntity instanceof EntityInterface ? $toEntity->getEntityName() : $toEntity;
        $fromEntity                 = $fromEntity instanceof EntityInterface ? $fromEntity->getEntityName() : $fromEntity;

        return SnakeCaseNaming::toSnakeCase(ValueString::camelToSnake($fromEntity), 'fk', ValueString::camelToSnake($toEntity)) . '_' . $key;
    }
}
