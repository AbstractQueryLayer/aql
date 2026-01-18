<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

/**
 * Interface for individual partition definition.
 *
 * Represents a single partition in a partitioned table:
 * PARTITION partition_name VALUES LESS THAN (value) [options]
 * or
 * PARTITION partition_name VALUES IN (value_list) [options]
 */
interface PartitionDefinitionInterface extends NodeInterface
{
    final public const string PARTITION_NAME = 'partitionName';

    final public const string VALUES_TYPE = 'valuesType';
     // 'LESS THAN' or 'IN'
    final public const string VALUES = 'values';

    final public const string OPTIONS = 'options';

    public function getPartitionName(): string;

    public function getValuesType(): ?string;

    /**
     * @return array<mixed>
     */
    public function getValues(): array;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
