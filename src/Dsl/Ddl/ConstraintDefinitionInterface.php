<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface ConstraintDefinitionInterface extends NodeInterface
{
    final public const string TABLE_NAME = 'tableName';

    final public const string COLUMNS = 'columns';

    final public const string REFERENCE_COLUMNS = 'referenceColumns';

    final public const string REFERENCE_ACTIONS = 'referenceActions';

    final public const string CONSTRAINT_NAME = 'constraintName';

    final public const string INDEX_NAME = 'indexName';

    public function getColumns(): array;

    public function getTableName(): string;

    public function getReferenceColumns(): array;

    public function getReferenceActions(): array;

    public function getConstraintName(): ?string;

    public function getIndexName(): ?string;

}
