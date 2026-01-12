<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeOptions;
use IfCastle\Exceptions\LogicalException;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class Table extends DdlStatementAbstract implements TableInterface
{
    public const string ACTION_CREATE = 'CREATE';

    protected string $comment       = '';

    public function __construct(
        protected string $tableName,
        array $columns,
        array $indexes              = [],
        array $constraints          = [],
        array $options              = [],
        array $partitions           = [],
        array $inherits             = [],
        protected bool $isTemporary  = false,
        protected bool $isIfNotExists = false
    ) {
        parent::__construct();
        $this->initChildNodes();

        $this->setColumns($columns)
            ->setIndexes($indexes)
            ->setConstraints($constraints)
            ->addOptions(...$options)
            ->setPartitions($partitions)
            ->setInherits($inherits);
    }

    protected function initChildNodes(): void
    {
        if ($this->childNodes === []) {
            $this->childNodes       = [
                self::COLUMNS       => new NodeList(),
                self::INDEXES       => new NodeList(),
                self::CONSTRAINTS   => new NodeList(),
                self::OPTIONS       => new NodeOptions(),
                self::INHERITS      => new NodeList(),
                self::PARTITIONS    => new NodeList(),
            ];
        }

        foreach ($this->childNodes as $node) {
            $node?->setParentNode($this);
        }
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::TABLE_NAME] ?? '',
            ArrayTyped::unserializeList($array[self::COLUMNS] ?? [], $validator),
            ArrayTyped::unserializeList($array[self::INDEXES] ?? [], $validator),
            ArrayTyped::unserializeList($array[self::CONSTRAINTS] ?? [], $validator),
            $array[self::OPTIONS] ?? [],
            $array[self::PARTITIONS] ?? [],
            $array[self::INHERITS] ?? []
        );
    }

    /**
     * @throws LogicalException
     */
    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::TABLE_NAME        => $this->tableName,
            self::COLUMNS           => ArrayTyped::serializeList($validator, ...$this->getColumns()),
            self::INDEXES           => ArrayTyped::serializeList($validator, ...$this->getIndexes()),
            self::CONSTRAINTS       => ArrayTyped::serializeList($validator, ...$this->getConstraints()),
            self::OPTIONS           => ArrayTyped::serializeList($validator, ...$this->getOptions()),
            self::PARTITIONS        => ArrayTyped::serializeList($validator, ...$this->getPartitions()),
            self::INHERITS          => ArrayTyped::serializeList($validator, ...$this->getInherits()),
        ];
    }

    #[\Override]
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return ColumnDefinition[]
     */
    #[\Override]
    public function getColumns(): array
    {
        return $this->childNodes[self::COLUMNS]?->getChildNodes() ?? [];
    }

    /**
     * @param ColumnDefinition[] $columns
     *
     * @return $this
     */
    public function setColumns(array $columns): static
    {
        $this->childNodes[self::COLUMNS]->addChildNode(...$columns);
        return $this;
    }

    public function addColumn(ColumnDefinitionInterface $column): static
    {
        $this->childNodes[self::COLUMNS]->addChildNode($column);

        return $this;
    }

    /**
     * @return IndexDefinition[]
     */
    #[\Override]
    public function getIndexes(): array
    {
        return $this->childNodes[self::INDEXES]?->getChildNodes() ?? [];
    }

    /**
     * @param IndexDefinition[] $indexes
     *
     * @return $this
     */
    public function setIndexes(array $indexes): static
    {
        $this->childNodes[self::INDEXES]->addChildNode(...$indexes);
        return $this;
    }

    public function addIndex(IndexDefinition $index): static
    {
        $this->childNodes[self::INDEXES]->addChildNode($index);

        return $this;
    }

    /**
     * @return ConstraintDefinition[]
     */
    #[\Override]
    public function getConstraints(): array
    {
        return $this->childNodes[self::CONSTRAINTS]?->getChildNodes() ?? [];
    }

    /**
     * @param ConstraintDefinition[] $constraints
     *
     * @return $this
     */
    public function setConstraints(array $constraints): static
    {
        $this->childNodes[self::CONSTRAINTS]->addChildNode(...$constraints);
        return $this;
    }

    public function addConstraint(ConstraintDefinitionInterface $constraint): static
    {
        $this->childNodes[self::CONSTRAINTS]->addChildNode($constraint);

        return $this;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getOptions(): array
    {
        return $this->childNodes[self::OPTIONS]?->getOptions() ?? [];
    }

    /**
     * @param string[] $options
     *
     * @return $this
     */
    public function addOptions(string ...$options): static
    {
        $this->childNodes[self::OPTIONS]->addOptions(...$options);

        return $this;
    }

    public function isOption(string $optionName): bool
    {
        return $this->childNodes[self::OPTIONS]?->isOption($optionName);
    }

    public function addOption(string $optionName): static
    {
        $this->childNodes[self::OPTIONS]->addOption($optionName);

        return $this;
    }

    public function removeOption(string $optionName): static
    {
        $this->childNodes[self::OPTIONS]->removeOption($optionName);
        return $this;
    }

    #[\Override]
    public function getPartitions(): array
    {
        return $this->childNodes[self::PARTITIONS]?->getChildNodes() ?? [];
    }

    public function setPartitions(array $partitions): static
    {
        $this->childNodes[self::PARTITIONS]->addChildNode(...$partitions);

        return $this;
    }

    public function getInherits(): array
    {
        return $this->childNodes[self::INHERITS]?->getChildNodes() ?? [];
    }

    public function setInherits(array $inherits): static
    {
        $this->childNodes[self::INHERITS]->addChildNode(...$inherits);

        return $this;
    }

    public function isForeign(string $columnName): bool
    {
        return $this->findForeignConstraint($columnName) !== null;
    }

    public function findForeignConstraint(string $columnName): ?ConstraintDefinition
    {
        foreach ($this->childNodes[self::CONSTRAINTS] as $constraint) {
            if (\in_array($columnName, $constraint->getColumns(), true)) {
                return $constraint;
            }
        }

        return null;
    }

    #[\Override]
    public function getComment(): string
    {
        return $this->comment;
    }


    public function setComment(string $comment): static
    {
        $this->comment              = $comment;
        return $this;
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql                        = [];

        $sql[]                      = $this->isTemporary ? 'CREATE TEMPORARY TABLE' : 'CREATE TABLE';

        if ($this->isIfNotExists) {
            $sql[]                  = 'IF NOT EXISTS';
        }

        $sql[]                      = $this->escape($this->tableName);
        $sql[]                      = "(\n";
        $internalSql                = $this->generateResultForChildNodes();

        $sql[]                      = \implode(",\n", $internalSql);

        $comment                    = $this->comment !== '' ? ' COMMENT ' . $this->quote($this->comment) : '';

        $sql[]                      = "\n){$comment}\n";

        return \implode(' ', $sql);
    }
}
