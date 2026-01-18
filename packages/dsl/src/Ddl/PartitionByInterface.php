<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

/**
 * Interface for PARTITION BY clause.
 *
 * Represents the complete partitioning specification:
 * PARTITION BY {HASH|KEY|RANGE|LIST} [LINEAR] [COLUMNS] (expression)
 * [PARTITIONS num]
 * [SUBPARTITION BY ...]
 * [(partition_definition [, partition_definition] ...)]
 */
interface PartitionByInterface extends NodeInterface
{
    final public const string PARTITION_TYPE = 'partitionType';
      // HASH, KEY, RANGE, LIST
    final public const string IS_LINEAR = 'isLinear';

    final public const string IS_COLUMNS = 'isColumns';

    final public const string EXPRESSION = 'expression';

    final public const string COLUMNS = 'columns';

    final public const string PARTITIONS_COUNT = 'partitionsCount';

    final public const string ALGORITHM = 'algorithm';
      // For KEY partitioning
    final public const string SUBPARTITION_BY = 'subpartitionBy';

    final public const string PARTITION_DEFINITIONS = 'partitionDefinitions';

    /**
     * Get partition type: HASH, KEY, RANGE, LIST.
     */
    public function getPartitionType(): string;

    /**
     * Check if LINEAR modifier is used.
     */
    public function isLinear(): bool;

    /**
     * Check if COLUMNS modifier is used (for RANGE COLUMNS or LIST COLUMNS).
     */
    public function isColumns(): bool;

    /**
     * Get partitioning expression (for HASH, RANGE, LIST without COLUMNS).
     */
    public function getExpression(): ?NodeInterface;

    /**
     * Get column list (for KEY or RANGE COLUMNS or LIST COLUMNS).
     *
     * @return string[]
     */
    public function getColumns(): array;

    /**
     * Get number of partitions (for HASH or KEY).
     */
    public function getPartitionsCount(): ?int;

    /**
     * Get algorithm version (for KEY partitioning).
     */
    public function getAlgorithm(): ?int;

    /**
     * Get subpartition specification.
     */
    public function getSubpartitionBy(): ?PartitionByInterface;

    /**
     * Get partition definitions.
     *
     * @return PartitionDefinitionInterface[]
     */
    public function getPartitionDefinitions(): array;
}
