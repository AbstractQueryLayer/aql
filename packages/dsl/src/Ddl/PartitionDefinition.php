<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

/**
 * Represents a single partition definition.
 *
 * Examples:
 * - PARTITION p0 VALUES LESS THAN (1991)
 * - PARTITION p1 VALUES IN (1, 2, 3)
 * - PARTITION p2 VALUES LESS THAN (2000) ENGINE = InnoDB
 */
class PartitionDefinition extends DdlStatementAbstract implements PartitionDefinitionInterface
{
    public function __construct(
        protected string $partitionName,
        protected ?string $valuesType = null,
        protected array $values = [],
        protected array $options = []
    ) {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::PARTITION_NAME] ?? '',
            $array[self::VALUES_TYPE] ?? null,
            $array[self::VALUES] ?? [],
            $array[self::OPTIONS] ?? []
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::PARTITION_NAME => $this->partitionName,
            self::VALUES_TYPE    => $this->valuesType,
            self::VALUES         => $this->values,
            self::OPTIONS        => $this->options,
        ];
    }

    #[\Override]
    public function getPartitionName(): string
    {
        return $this->partitionName;
    }

    #[\Override]
    public function getValuesType(): ?string
    {
        return $this->valuesType;
    }

    #[\Override]
    public function getValues(): array
    {
        return $this->values;
    }

    #[\Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql = [];
        $sql[] = 'PARTITION ' . $this->escape($this->partitionName);

        if ($this->valuesType !== null && $this->values !== []) {
            $valuesStr = \implode(', ', \array_map(
                fn($v) => \is_string($v) ? $this->quote($v) : (string) $v,
                $this->values
            ));

            if ($this->valuesType === 'LESS THAN') {
                $sql[] = "VALUES LESS THAN ($valuesStr)";
            } elseif ($this->valuesType === 'IN') {
                $sql[] = "VALUES IN ($valuesStr)";
            }
        }

        // Add partition options if any (ENGINE, COMMENT, DATA DIRECTORY, etc.)
        foreach ($this->options as $key => $value) {
            $sql[] = \strtoupper((string) $key) . ' = ' . (\is_string($value) ? $this->quote($value) : $value);
        }

        return \implode(' ', $sql);
    }
}
