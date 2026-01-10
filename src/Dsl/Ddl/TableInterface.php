<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface TableInterface extends NodeInterface
{
    final public const string TABLE_NAME    = 'tableName';

    final public const string COLUMNS       = 'columns';

    final public const string INDEXES       = 'indexes';

    final public const string CONSTRAINTS   = 'constraints';

    final public const string OPTIONS       = 'options';

    // See: https://www.postgresql.org/docs/current/sql-createtable.html
    final public const string INHERITS      = 'inherits';

    final public const string PARTITIONS    = 'partitions';

    final public const string COMMENT       = 'comment';


    public function getTableName(): string;

    public function getColumns(): array;

    public function setColumns(array $columns): static;

    public function addColumn(ColumnDefinitionInterface $column): static;

    public function getIndexes(): array;

    public function setIndexes(array $indexes): static;

    public function addIndex(IndexDefinition $index): static;

    public function getConstraints(): array;

    public function setConstraints(array $constraints): static;

    public function addConstraint(ConstraintDefinitionInterface $constraint): static;

    /**
     * @return array<string>
     */
    public function getOptions(): array;

    public function isOption(string $optionName): bool;

    public function addOption(string $optionName): static;

    public function removeOption(string $optionName): static;

    /**
     * @param string[] $options
     *
     * @return $this
     */
    public function addOptions(string ...$options): static;

    public function getPartitions(): array;

    public function setPartitions(array $partitions): static;

    public function getInherits(): array;

    public function setInherits(array $inherits): static;

    public function isForeign(string $columnName): bool;

    public function findForeignConstraint(string $columnName): ?ConstraintDefinition;

    public function getComment(): string;

    public function setComment(string $comment): static;
}
