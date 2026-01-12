<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class ConstraintDefinition extends DdlStatementAbstract implements ConstraintDefinitionInterface
{
    public function __construct(
        protected array $columns,
        protected string $tableName,
        protected array $referenceColumns,
        protected array $referenceActions,
        protected ?string $constraintName = null,
        protected ?string $indexName = null)
    {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::COLUMNS] ?? [],
            $array[self::TABLE_NAME] ?? '',
            $array[self::REFERENCE_COLUMNS] ?? [],
            $array[self::REFERENCE_ACTIONS] ?? [],
            $array[self::CONSTRAINT_NAME] ?? null,
            $array[self::INDEX_NAME] ?? null
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::COLUMNS               => ArrayTyped::unserializeList($validator, ...$this->columns),
            self::TABLE_NAME            => $this->tableName,
            self::REFERENCE_COLUMNS     => ArrayTyped::unserializeList($validator, ...$this->referenceColumns),
            self::REFERENCE_ACTIONS     => ArrayTyped::unserializeList($validator, ...$this->referenceActions),
            self::CONSTRAINT_NAME       => $this->constraintName,
            self::INDEX_NAME            => $this->indexName,
        ];
    }

    #[\Override]
    public function getColumns(): array
    {
        return $this->columns;
    }

    #[\Override]
    public function getTableName(): string
    {
        return $this->tableName;
    }

    #[\Override]
    public function getReferenceColumns(): array
    {
        return $this->referenceColumns;
    }

    #[\Override]
    public function getReferenceActions(): array
    {
        return $this->referenceActions;
    }

    #[\Override]
    public function getConstraintName(): ?string
    {
        return $this->constraintName;
    }

    #[\Override]
    public function getIndexName(): ?string
    {
        return $this->indexName;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql                        = [];

        $sql[]                      = 'CONSTRAINT';

        if ($this->constraintName !== null) {
            $sql[]                  = $this->escape($this->constraintName);
        }

        $sql[]                      = 'FOREIGN KEY';

        if ($this->indexName !== null) {
            $sql[]                  = $this->escape($this->indexName);
        }

        if ($this->columns !== []) {

            $columns                = [];

            foreach ($this->columns as $column) {
                $columns[]          = $this->escape($column);
            }

            $sql[]                  = '(' . \implode(',', $columns) . ')';
        }

        $sql[]                      = 'REFERENCES';
        $sql[]                      = $this->escape($this->tableName);

        $columns                    = [];

        foreach ($this->referenceColumns as $column) {
            $columns[]              = $this->escape($column);
        }

        if ($columns !== []) {
            $sql[]                      = '(' . \implode(',', $columns) . ')';
        }

        foreach ($this->referenceActions as $action => $option) {
            $sql[]                  = \sprintf('ON %s %s', $action, $option);
        }

        return \implode(' ', $sql);
    }
}
