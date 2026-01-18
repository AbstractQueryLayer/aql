<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

/**
 * Represents PARTITION BY clause for table partitioning.
 *
 * Supports all MySQL 8.4 partitioning methods:
 * - PARTITION BY HASH(expr) PARTITIONS num
 * - PARTITION BY [LINEAR] KEY [ALGORITHM={1|2}] (column_list) PARTITIONS num
 * - PARTITION BY RANGE(expr) (partition_definition [, ...])
 * - PARTITION BY RANGE COLUMNS(column_list) (partition_definition [, ...])
 * - PARTITION BY LIST(expr) (partition_definition [, ...])
 * - PARTITION BY LIST COLUMNS(column_list) (partition_definition [, ...])
 *
 * With optional SUBPARTITION BY for subpartitioning.
 */
class PartitionBy extends DdlStatementAbstract implements PartitionByInterface
{
    public function __construct(
        protected string $partitionType,
        protected bool $isLinear = false,
        protected bool $isColumns = false,
        protected ?NodeInterface $expression = null,
        protected array $columns = [],
        protected ?int $partitionsCount = null,
        protected ?int $algorithm = null,
        protected ?PartitionByInterface $subpartitionBy = null,
        protected array $partitionDefinitions = []
    ) {
        parent::__construct();

        if ($expression !== null) {
            $this->childNodes[self::EXPRESSION] = $expression;
        }

        if ($partitionDefinitions !== []) {
            $this->childNodes[self::PARTITION_DEFINITIONS] = new NodeList(...$partitionDefinitions);
        }

        if ($subpartitionBy !== null) {
            $this->childNodes[self::SUBPARTITION_BY] = $subpartitionBy;
        }
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::PARTITION_TYPE] ?? 'HASH',
            $array[self::IS_LINEAR] ?? false,
            $array[self::IS_COLUMNS] ?? false,
            isset($array[self::EXPRESSION]) ? ArrayTyped::unserialize($array[self::EXPRESSION], $validator) : null,
            $array[self::COLUMNS] ?? [],
            $array[self::PARTITIONS_COUNT] ?? null,
            $array[self::ALGORITHM] ?? null,
            isset($array[self::SUBPARTITION_BY]) ? ArrayTyped::unserialize($array[self::SUBPARTITION_BY], $validator) : null,
            ArrayTyped::unserializeList($array[self::PARTITION_DEFINITIONS] ?? [], $validator)
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::PARTITION_TYPE         => $this->partitionType,
            self::IS_LINEAR              => $this->isLinear,
            self::IS_COLUMNS             => $this->isColumns,
            self::EXPRESSION             => $this->expression !== null ? ArrayTyped::serialize($this->expression, $validator) : null,
            self::COLUMNS                => $this->columns,
            self::PARTITIONS_COUNT       => $this->partitionsCount,
            self::ALGORITHM              => $this->algorithm,
            self::SUBPARTITION_BY        => $this->subpartitionBy !== null ? ArrayTyped::serialize($this->subpartitionBy, $validator) : null,
            self::PARTITION_DEFINITIONS  => ArrayTyped::serializeList($validator, ...$this->partitionDefinitions),
        ];
    }

    #[\Override]
    public function getPartitionType(): string
    {
        return $this->partitionType;
    }

    #[\Override]
    public function isLinear(): bool
    {
        return $this->isLinear;
    }

    #[\Override]
    public function isColumns(): bool
    {
        return $this->isColumns;
    }

    #[\Override]
    public function getExpression(): ?NodeInterface
    {
        return $this->expression;
    }

    #[\Override]
    public function getColumns(): array
    {
        return $this->columns;
    }

    #[\Override]
    public function getPartitionsCount(): ?int
    {
        return $this->partitionsCount;
    }

    #[\Override]
    public function getAlgorithm(): ?int
    {
        return $this->algorithm;
    }

    #[\Override]
    public function getSubpartitionBy(): ?PartitionByInterface
    {
        return $this->subpartitionBy;
    }

    #[\Override]
    public function getPartitionDefinitions(): array
    {
        return $this->partitionDefinitions;
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql = [];

        // PARTITION BY
        $sql[] = 'PARTITION BY';

        // LINEAR modifier
        if ($this->isLinear) {
            $sql[] = 'LINEAR';
        }

        // Partition type
        $sql[] = $this->partitionType;

        // COLUMNS modifier
        if ($this->isColumns) {
            $sql[] = 'COLUMNS';
        }

        // ALGORITHM (for KEY partitioning)
        if ($this->algorithm !== null) {
            $sql[] = "ALGORITHM = {$this->algorithm}";
        }

        // Expression or columns
        if ($this->expression !== null) {
            $sql[] = '(' . $this->expression->getAql() . ')';
        } elseif ($this->columns !== []) {
            $escapedColumns = \array_map($this->escape(...), $this->columns);
            $sql[] = '(' . \implode(', ', $escapedColumns) . ')';
        }

        // PARTITIONS count
        if ($this->partitionsCount !== null) {
            $sql[] = "PARTITIONS {$this->partitionsCount}";
        }

        // SUBPARTITION BY
        if ($this->subpartitionBy !== null) {
            $sql[] = $this->subpartitionBy->getAql();
        }

        // Partition definitions
        if ($this->partitionDefinitions !== []) {
            $definitions = [];
            foreach ($this->partitionDefinitions as $definition) {
                if ($definition instanceof PartitionDefinitionInterface) {
                    $definitions[] = '    ' . $definition->getAql();
                }
            }

            if ($definitions !== []) {
                $sql[] = "(\n" . \implode(",\n", $definitions) . "\n)";
            }
        }

        return \implode(' ', $sql);
    }
}
