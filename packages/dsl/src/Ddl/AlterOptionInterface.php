<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;

interface AlterOptionInterface extends NodeInterface
{
    final public const string WHAT          = 'what';

    final public const string ACTION        = 'action';

    final public const string DEFINITIONS   = 'definitions';

    final public const string TABLE_OPTIONS = 'tableOptions';

    public function getAction(): string;

    public function setAction(string $action): static;

    public function getWhat(): string;

    public function setWhat(string $what): static;

    /**
     * @return NodeList<NodeInterface>
     */
    public function getDefinitions(): NodeList;

    /**
     * @return NodeList<NodeInterface>
     */
    public function getTableOptions(): NodeList;

    public function setTableOptions(array $tableOptions): static;
}
