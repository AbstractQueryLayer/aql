<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder\NamingStrategy;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Dsl\Ddl\TableInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Functions\StoredProcedureInterface;
use IfCastle\AQL\Entity\Functions\TriggerInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;

class CamelCaseNaming implements NamingStrategyInterface
{
    public static function toCamelCase(string...$words): string
    {
        if ($words === []) {
            return '';
        }

        $result                     = \array_shift($words);

        return \lcfirst($result) . \implode('', \array_map(ucfirst(...), $words));
    }

    public static function toCamelCaseFirstUpper(string...$words): string
    {
        return \ucfirst(static::toCamelCase(...$words));
    }

    #[\Override]
    public function generateTableName(array|EntityInterface $entity): string
    {
        return $entity instanceof EntityInterface ? $entity->getEntityName() : static::toCamelCase(...$entity);
    }

    #[\Override]
    public function generateEntityName(TableInterface|array $table, ?string $role = null): string
    {
        return $table instanceof TableInterface ? $table->getTableName() : static::toCamelCaseFirstUpper(...$table);
    }

    #[\Override]
    public function generateCrossReferenceEntityName(string|EntityInterface $fromEntity, string|EntityInterface $toEntity): string
    {
        $toEntity                   = $toEntity instanceof EntityInterface ? $toEntity->getEntityName() : $toEntity;
        $fromEntity                 = $fromEntity instanceof EntityInterface ? $fromEntity->getEntityName() : $fromEntity;

        return static::toCamelCaseFirstUpper($fromEntity, 'to', $toEntity);
    }

    #[\Override]
    public function generateColumnName(PropertyInterface|array $property, string|EntityInterface|null $entity = null): string
    {
        return $property instanceof PropertyInterface ? $property->getName() : static::toCamelCase(...$property);
    }

    #[\Override]
    public function generatePropertyName(ColumnDefinitionInterface|array $column, string|EntityInterface|null $entity = null): string
    {
        return $column instanceof ColumnDefinitionInterface ? $column->getColumnName() : static::toCamelCase(...$column);
    }

    #[\Override]
    public function generateKeyName(KeyInterface|array $key, string|EntityInterface|null $entity = null): string
    {
        return $key instanceof KeyInterface ? $key->getKeyName() : static::toCamelCase(...$key);
    }

    #[\Override]
    public function generateTriggerName(array|TriggerInterface $trigger, EntityInterface|string|null $entity = null): string
    {
        return $trigger instanceof TriggerInterface ? $trigger->getTriggerName() : static::toCamelCase(...$trigger);
    }

    #[\Override]
    public function generateProcedureName(StoredProcedureInterface|array $procedure, string|EntityInterface|null $entity = null): string
    {
        return $procedure instanceof StoredProcedureInterface ? $procedure->getFunctionName() : static::toCamelCase(...$procedure);
    }

    #[\Override]
    public function generateConstraintName(string|EntityInterface $fromEntity, string|EntityInterface $toEntity, string $key): string
    {
        $toEntity                   = $toEntity instanceof EntityInterface ? $toEntity->getEntityName() : $toEntity;
        $fromEntity                 = $fromEntity instanceof EntityInterface ? $fromEntity->getEntityName() : $fromEntity;

        return static::toCamelCaseFirstUpper($fromEntity, 'fk', $toEntity, $key);
    }
}
