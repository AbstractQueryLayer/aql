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
use IfCastle\TypeDefinitions\Value\ValueString;

class SnakeCaseNaming implements NamingStrategyInterface
{
    public static function toSnakeCase(string...$words): string
    {
        if ($words === []) {
            return '';
        }

        return \strtolower(\implode('_', $words));
    }

    #[\Override]
    public function generateTableName(array|EntityInterface $entity): string
    {
        return $entity instanceof EntityInterface ? ValueString::camelToSnake($entity->getEntityName()) : static::toSnakeCase(...$entity);
    }

    #[\Override]
    public function generateEntityName(TableInterface|array $table, ?string $role = null): string
    {
        return $table instanceof TableInterface ? ValueString::camelToSnake($table->getTableName()) : static::toSnakeCase(...$table);
    }

    #[\Override]
    public function generateCrossReferenceEntityName(string|EntityInterface $fromEntity, string|EntityInterface $toEntity): string
    {
        $toEntity                   = $toEntity instanceof EntityInterface ? $toEntity->getEntityName() : $toEntity;
        $fromEntity                 = $fromEntity instanceof EntityInterface ? $fromEntity->getEntityName() : $fromEntity;

        return static::toSnakeCase($fromEntity, 'to', $toEntity);
    }

    #[\Override]
    public function generateColumnName(PropertyInterface|array $property, string|EntityInterface|null $entity = null): string
    {
        return $property instanceof PropertyInterface ? ValueString::camelToSnake($property->getName()) : static::toSnakeCase(...$property);
    }

    #[\Override]
    public function generatePropertyName(ColumnDefinitionInterface|array $column, string|EntityInterface|null $entity = null): string
    {
        return $column instanceof ColumnDefinitionInterface ? ValueString::camelToSnake($column->getColumnName()) : static::toSnakeCase(...$column);
    }

    #[\Override]
    public function generateKeyName(KeyInterface|array $key, string|EntityInterface|null $entity = null): string
    {
        return $key instanceof KeyInterface ? ValueString::camelToSnake($key->getKeyName()) : static::toSnakeCase(...$key);
    }

    #[\Override]
    public function generateTriggerName(array|TriggerInterface $trigger, EntityInterface|string|null $entity = null): string
    {
        return $trigger instanceof TriggerInterface ? ValueString::camelToSnake($trigger->getTriggerName()) : static::toSnakeCase(...$trigger);
    }

    #[\Override]
    public function generateProcedureName(StoredProcedureInterface|array $procedure, string|EntityInterface|null $entity = null): string
    {
        return $procedure instanceof StoredProcedureInterface ? ValueString::camelToSnake($procedure->getFunctionName()) : static::toSnakeCase(...$procedure);
    }

    #[\Override]
    public function generateConstraintName(string|EntityInterface $fromEntity, string|EntityInterface $toEntity, string $key): string
    {
        $toEntity                   = $toEntity instanceof EntityInterface ? $toEntity->getEntityName() : $toEntity;
        $fromEntity                 = $fromEntity instanceof EntityInterface ? $fromEntity->getEntityName() : $fromEntity;

        return static::toSnakeCase($fromEntity, 'to', $toEntity, $key);
    }
}
