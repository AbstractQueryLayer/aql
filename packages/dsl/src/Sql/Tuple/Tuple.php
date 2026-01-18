<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\Exceptions\RequiredValueEmpty;

class Tuple extends NodeAbstract implements TupleInterface
{
    /**
     * whether a tuple is a default list of columns.
     */
    protected bool $whetherDefault  = false;

    private int $hiddenColumnsAliasIndex = 0;

    /**
     *
     * @throws RequiredValueEmpty
     */
    public function __construct(TupleColumnInterface|string ...$columns)
    {
        parent::__construct();

        $this->childNodes = [
            self::NODE_COLUMNS         => new NodeList(),
            self::NODE_HIDDEN_COLUMNS  => new NodeList(),
        ];

        foreach ($columns as $column) {
            $this->addTupleColumn($column instanceof TupleColumnInterface ? $column : new TupleColumn(new Column($column)));
        }
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $results                    = [];

        if ($this->childNodes[self::NODE_COLUMNS]->getChildNodes() === [] && $this->whetherDefault) {
            $results[]              = '*';
        }

        foreach ($this->childNodes[self::NODE_COLUMNS] as $childNode) {

            $aql                    = $childNode?->getAql($forResolved);

            if (!empty($aql)) {
                $results[]          = $aql;
            }
        }

        $hiddenResults          = [];

        foreach ($this->getHiddenColumns() as $hiddenColumn) {
            $aql                = $hiddenColumn?->getAql($forResolved);

            if (!empty($aql)) {
                $hiddenResults[] = $aql;
            }
        }

        if ($results === [] && $hiddenResults === []) {
            return '';
        }

        $results                = \implode(', ', $results);
        $hiddenResults          = \implode(', ', $hiddenResults);

        if ($hiddenResults !== '') {
            $hiddenResults      = '[[' . $hiddenResults . ']]';

            if ($results !== '') {
                $hiddenResults  = ', ' . $hiddenResults;
            }
        }

        return $results . $hiddenResults;
    }

    #[\Override]
    public function whetherDefault(): bool
    {
        return $this->whetherDefault;
    }

    #[\Override]
    public function getTupleColumns(): array
    {
        return $this->childNodes[self::NODE_COLUMNS]->getChildNodes();
    }

    #[\Override]
    public function findTupleColumn(string $alias): ?TupleColumnInterface
    {
        return $this->childNodes[self::NODE_COLUMNS][$alias] ?? null;
    }

    #[\Override]
    public function getHiddenColumns(): array
    {
        return $this->childNodes[self::NODE_HIDDEN_COLUMNS]->getChildNodes();
    }

    /**
     * @throws RequiredValueEmpty
     */
    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof TupleColumnInterface) {
                $this->addTupleColumn($node);
            } else {
                throw new TransformationException([
                    'template'          => 'Tuple can only have TupleColumnInterface as child nodes, but got {type}',
                    'type'              => \get_debug_type($node),
                ]);
            }
        }
    }

    /**
     * @throws RequiredValueEmpty
     * @throws TransformationException
     */
    #[\Override]
    public function addTupleColumn(TupleColumnInterface|NodeInterface $tupleColumn, ?string $alias = null): static
    {
        if ($tupleColumn instanceof TupleColumnInterface && $alias === null) {
            $alias                  = $tupleColumn->getAliasOrColumnNameOrNull();
        }

        $columns                    = & $this->childNodes[self::NODE_COLUMNS];

        if ($alias !== null && \array_key_exists($alias, $columns->getChildNodes())) {
            throw new TransformationException([
                'template'          => 'Trying to add a column with the same name {alias} twice. Please use alias',
                'alias'             => $alias,
            ]);
        }

        if ($tupleColumn instanceof TupleColumnInterface) {
            $columns[$alias]        = $tupleColumn->setParentNode($this);
            return $this;
        }

        // Auto generates alias for function reference as function name
        if ($alias === null && $tupleColumn instanceof FunctionReferenceInterface) {
            $alias                  = $tupleColumn->getFunctionName();
        }

        if ($alias === null) {
            throw new RequiredValueEmpty('$alias', 'string');
        }

        $columns[$alias]            = new TupleColumn($tupleColumn, $alias)->setParentNode($this);

        return $this;
    }

    /**
     * @throws RequiredValueEmpty
     * @throws TransformationException
     */
    #[\Override]
    public function addColumnIfNoExists(TupleColumnInterface $newColumn): TupleColumnInterface
    {
        $columnField                = $newColumn->getExpression();

        if (false === $columnField instanceof ColumnInterface) {
            throw new TransformationException([
                'template'          => 'TupleColumn should have Column type, but got {type}',
                'type'              => \get_debug_type($columnField),
            ]);
        }

        foreach ($this->getTupleColumns() as $column) {
            if ($column->isColumnEqual($newColumn)) {
                return $column;
            }
        }

        $this->addTupleColumn($newColumn);

        return $newColumn;
    }

    #[\Override]
    public function addColumnIfNoExistsByAlias(TupleColumnInterface $newColumn): TupleColumnInterface|null
    {
        $columnField                = $newColumn->getExpression();
        $alias                      = $newColumn->getAliasOrColumnNameOrNull();

        if (false === $columnField instanceof ColumnInterface || empty($alias)) {
            throw new TransformationException([
                'template'          => 'TupleColumn should have Column type, but got {type}',
                'type'              => \get_debug_type($columnField),
            ]);
        }

        foreach ($this->getTupleColumns() as $column) {

            if ($alias !== $column->getAliasOrColumnNameOrNull()) {
                continue;
            }

            if ($column->isColumnEqual($newColumn)) {
                return $column;
            }

            return null;

        }

        $this->addTupleColumn($newColumn);

        return null;
    }

    #[\Override]
    public function addHiddenColumn(TupleColumnInterface|NodeInterface $tupleColumn, ?string $alias = null): static
    {
        $hiddenColumns              = & $this->childNodes[self::NODE_HIDDEN_COLUMNS];

        if ($tupleColumn instanceof TupleColumnInterface) {
            $hiddenColumns[$tupleColumn->getAliasOrColumnNameOrNull()] = $tupleColumn;
            return $this;
        }

        if ($alias === null) {
            $alias                  = $this->generateAliasForHiddenColumn();
        }

        $hiddenColumns[$alias]      = new TupleColumn($tupleColumn, $alias);

        return $this;
    }

    /**
     * @throws RequiredValueEmpty
     */
    #[\Override]
    public function resolveHiddenColumn(ColumnInterface $column, ?callable $aliasGenerator = null): TupleColumnInterface
    {
        foreach ($this->getHiddenColumns() as $hiddenColumn) {

            $expression             = $hiddenColumn->getExpression();

            if ($expression instanceof ColumnInterface
                && $expression->isEqual($column)) {
                return $hiddenColumn;
            }
        }

        $tupleColumn                = new TupleColumn(
            $column, $aliasGenerator !== null ? $aliasGenerator() : $this->generateAliasForHiddenColumn()
        );

        $this->addHiddenColumn($tupleColumn);

        return $tupleColumn;
    }

    #[\Override]
    public function isDefaultColumns(): bool
    {
        return $this->whetherDefault;
    }

    #[\Override]
    public function markAsDefaultColumns(): static
    {
        $this->whetherDefault       = true;
        return $this;
    }

    #[\Override]
    public function generateAliasForHiddenColumn(): string
    {
        return '@' . ($this->hiddenColumnsAliasIndex++);
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $results                = [];

        if ($this->childNodes[self::NODE_COLUMNS]->isEmpty() && $this->whetherDefault) {
            $results[]          = '*';
        } else {
            $columns            = $this->childNodes[self::NODE_COLUMNS]->getResult();

            if ($columns !== '') {
                $results[]      = $columns;
            }
        }

        $hiddenResults          = [];

        foreach ($this->getHiddenColumns() as $hiddenColumn) {
            $sql                = $hiddenColumn?->getResult();

            if (!empty($sql)) {
                $hiddenResults[] = $sql;
            }
        }

        return \implode(', ', \array_merge($results, $hiddenResults));
    }
}
