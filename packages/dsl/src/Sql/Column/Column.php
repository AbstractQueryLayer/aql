<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Column;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;

class Column extends NodeAbstract implements ColumnInterface
{
    /**
     * Create column placeholder.
     *
     *
     */
    public static function placeholder(string $columnName): static
    {
        return new self($columnName)->markAsPlaceholder();
    }

    protected ?string $fieldName    = null;

    /**
     * DataBase table.
     */
    protected ?string $subject      = null;

    protected ?string $subjectAlias = null;

    protected ?DefinitionMutableInterface $definition = null;

    protected bool $isPlaceholder   = false;

    public function __construct(
        protected string $columnName,
        protected ?string $entityName   = null,
        /**
         * Flag for reference to Foreign Entity.
         */
        protected bool $isForeign       = false
    ) {
        parent::__construct();
    }

    #[\Override]
    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    #[\Override]
    public function getSubjectAlias(): ?string
    {
        return $this->subjectAlias;
    }

    #[\Override]
    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    #[\Override]
    public function setFieldName(string $fieldName): static
    {
        $this->fieldName            = $fieldName;
        return $this;
    }

    #[\Override]
    public function setEntityName(string $name): static
    {
        $this->entityName           = $name;
        return $this;
    }

    #[\Override]
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    #[\Override]
    public function setSubject(string $subject): static
    {
        $this->subject              = $subject;
        return $this;
    }

    #[\Override]
    public function isForeign(): bool
    {
        return $this->isForeign;
    }

    #[\Override]
    public function reverseForeign(): static
    {
        $this->isForeign           = !$this->isForeign;

        return $this;
    }

    #[\Override]
    public function isPlaceholder(): bool
    {
        return $this->isPlaceholder;
    }

    #[\Override]
    public function markAsPlaceholder(): static
    {
        $this->isTransformed = true;
        $this->isPlaceholder = true;

        return $this;
    }

    #[\Override]
    public function getDefinition(): ?DefinitionInterface
    {
        return $this->definition;
    }

    #[\Override]
    public function setDefinition(DefinitionMutableInterface $definition): static
    {
        $this->definition           = $definition;
        return $this;
    }

    #[\Override]
    public function setSubjectAlias(string $subjectAlias): static
    {
        $this->subjectAlias         = $subjectAlias;
        return $this;
    }

    #[\Override]
    public function getColumnName(): string
    {
        return $this->columnName;
    }

    #[\Override]
    public function isEqual(ColumnInterface|string $column): bool
    {
        if (\is_string($column)) {
            return $column === $this->columnName;
        }

        if ($this->entityName !== null && $column->getEntityName() !== null && $this->entityName !== $column->getEntityName()) {
            return false;
        }

        return $this->columnName === $column->getColumnName();
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->entityName === null) {
            return $this->columnName;
        }

        return $this->entityName . '.' . $this->columnName;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->isPlaceholder()) {
            // return placeholder as (empty string)
            // for expression
            // '' as alias1, '' as alias2
            return '\'\'';
        }

        if ($this->fieldName === null || $this->fieldName === '') {
            return '';
        }

        $subject                    = $this->subjectAlias ?? $this->subject;

        return $subject === null || $subject === '' ?
            $this->escape($this->fieldName)
            : $this->escape($subject) . '.' . $this->escape($this->fieldName);
    }
}
