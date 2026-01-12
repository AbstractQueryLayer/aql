<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * SQL Expression (`column1`, `column2`, ...).
 */
class ColumnList extends NodeAbstract implements ColumnListInterface
{
    /**
     * @throws ParseException
     */
    public function __construct(ColumnInterface|string ...$columns)
    {
        parent::__construct();

        foreach ($columns as $column) {
            $this->appendColumn($column);
        }
    }

    #[\Override]
    public function getColumns(): array
    {
        return $this->childNodes;
    }

    #[\Override]
    public function findColumn(string|ColumnInterface $name): ?ColumnInterface
    {
        foreach ($this->childNodes as $column) {
            if ($column instanceof ColumnInterface && $column->isEqual($name)) {
                return $column;
            }
        }

        return null;
    }

    #[\Override]
    public function findColumnOffset(ColumnInterface|string $name): ?int
    {
        foreach ($this->childNodes as $offset => $column) {
            if ($column instanceof ColumnInterface && $column->isEqual($name)) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof ColumnInterface) {
                $this->childNodes[] = $node->setParentNode($this);
            } else {
                throw new UnexpectedValueType('node', $node, ColumnInterface::class);
            }
        }
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function appendColumn(ColumnInterface|string $column): static
    {
        if (\is_string($column)) {
            $column                  = new Column($column);
        }

        if ($column instanceof ColumnInterface === false) {
            throw new ParseException('$column should be instance of ColumnI');
        }

        $this->childNodes[]         = $column->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '(' . $this->nodesToAql($this->childNodes, ', ', $forResolved) . ')';
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if (\count($result) === 1) {
            return $result[0];
        }

        return '(' . \implode(', ', $result) . ')';
    }
}
