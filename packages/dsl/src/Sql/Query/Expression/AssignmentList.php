<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Node\NullNode;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\Exceptions\UnexpectedValueType;

class AssignmentList extends NodeAbstract implements AssignmentListInterface
{
    protected bool $isValueSyntax   = false;

    /**
     * @throws UnexpectedValueType
     */
    public static function fromKeyValue(array $keyValue): static
    {
        $nodes                      = [];

        foreach ($keyValue as $key => $value) {

            if ($value instanceof Assign) {
                $nodes[]                = $value;
                continue;
            }

            if (!\is_string($key)) {
                throw new UnexpectedValueType('$key', $key, 'string');
            }

            $nodes[]                = new Assign(new Column($key), new Constant($value));
        }

        return new self(...$nodes);
    }

    #[\Override]
    public function isValueSyntax(): bool
    {
        return $this->isValueSyntax;
    }

    #[\Override]
    public function asValueSyntax(): static
    {
        $this->isValueSyntax        = true;
        return $this;
    }

    #[\Override]
    public function isListEmpty(): bool
    {
        return $this->childNodes === [];
    }

    #[\Override]
    public function isListNotEmpty(): bool
    {
        return $this->childNodes !== [];
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Assign) {
                $this->childNodes[] = $node->setParentNode($this);
            } else {
                throw new UnexpectedValueType('node', $node, Assign::class);
            }
        }
    }

    #[\Override]
    public function addAssign(Assign $assign): static
    {
        $this->childNodes[]         = $assign->setParentNode($this);
        return $this;
    }

    #[\Override]
    public function addAssignment(string|ColumnInterface $column, NodeInterface $rightSide): static
    {
        $this->childNodes[]         = new Assign(
            $column instanceof ColumnInterface ? $column : new Column($column), $rightSide
        )->setParentNode($this);

        return $this->needTransform();
    }

    #[\Override]
    public function assign(string $column, float|bool|int|string|null $value): static
    {
        $this->childNodes[]         = new Assign(new Column($column), new Variable($value))->setParentNode($this);

        return $this->needTransform();
    }

    #[\Override]
    public function findAssignByColumn(ColumnInterface|string $column): ?Assign
    {
        foreach ($this->childNodes as $assign) {
            /* @var $assign Assign */
            if ($assign->getLeftKey()?->isEqual($column) === true) {
                return $assign;
            }
        }

        return null;
    }

    #[\Override]
    public function findRightNodes(string|ColumnInterface ...$columns): array
    {
        $results                    = [];

        foreach ($columns as $column) {
            $results[$column instanceof ColumnInterface ? $column->getColumnName() : $column] =
                $this->findAssignByColumn($column)?->getRightNode();
        }

        return $results;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $results                    = $this->childNodesToAql(', ');

        if ($results === '') {
            return '';
        }

        return 'SET ' . $results;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->isValueSyntax) {
            return $this->generateValueSyntax();
        }

        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return null;
        }

        return 'SET ' . \implode(', ', $result);
    }

    /**
     * @throws TransformationException
     */
    protected function generateValueSyntax(): string
    {
        $columns                    = [];
        $values                     = [];

        foreach ($this->childNodes as $childNode) {

            $childNode              = $childNode?->resolveNode();
            if ($childNode === null) {
                continue;
            }

            if ($childNode instanceof NullNode) {
                continue;
            }

            if ($childNode instanceof Assign === false) {
                throw new TransformationException([
                    'template'      => 'Invalid node type for values expression {type} expected Assign',
                    'type'          => \get_debug_type($childNode),
                ]);
            }

            $value                  = $childNode->getRightNode()?->getResult();

            if ($value === null) {
                continue;
            }

            $columnName             = $childNode->getLeftKey()?->getColumnName();

            if ($columnName === null || $columnName === '') {
                throw new TransformationException([
                    'template'      => 'Assign.leftKey is empty for {node}',
                    'node'          => \get_debug_type($childNode),
                ]);
            }

            $columns[]              = $this->escape($columnName);
            $values[]               = $value;
        }

        if ($columns === []) {
            return '';
        }

        return '(' . \implode(', ', $columns) . ') VALUES (' . \implode(', ', $values) . ')';
    }
}
