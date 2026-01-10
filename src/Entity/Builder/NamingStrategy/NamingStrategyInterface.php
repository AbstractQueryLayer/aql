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

/**
 * Database object naming strategy.
 * Used to automatically create names according to different schemes.
 * You can use this strategy to name fields according to: snake_case and DTO properties camelCase.
 */
interface NamingStrategyInterface
{
    /**
     * @param EntityInterface|string[] $entity
     */
    public function generateTableName(EntityInterface|array $entity): string;

    public function generateEntityName(TableInterface|array $table, ?string $role = null): string;

    public function generateCrossReferenceEntityName(EntityInterface|string $fromEntity, EntityInterface|string $toEntity): string;

    /**
     * @param   PropertyInterface|string[]  $property
     */
    public function generateColumnName(PropertyInterface|array $property, EntityInterface|string|null $entity = null): string;

    /**
     * @param   ColumnDefinitionInterface|string[] $column
     */
    public function generatePropertyName(ColumnDefinitionInterface|array $column, EntityInterface|string|null $entity = null): string;

    /**
     * @param   KeyInterface|string[]       $key
     */
    public function generateKeyName(KeyInterface|array $key, EntityInterface|string|null $entity = null): string;

    /**
     * @param   TriggerInterface|string[]   $trigger
     */
    public function generateTriggerName(TriggerInterface|array $trigger, EntityInterface|string|null $entity = null): string;

    public function generateProcedureName(StoredProcedureInterface|array $procedure, EntityInterface|string|null $entity = null): string;

    public function generateConstraintName(EntityInterface|string $fromEntity, EntityInterface|string $toEntity, string $key): string;
}
