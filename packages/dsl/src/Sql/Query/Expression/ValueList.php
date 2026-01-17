<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\OperationInterface;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * SQL Expression (`column1`, `column2`, ...) VALUES (1, 2, ...), (3, 4, ...), ...
 * or
 * SQL Expression (`column1`, `column2`, ...) IN ((1, 2, ...), (3, 4, ...), ...).
 *
 */
class ValueList extends NodeAbstract implements OperationInterface, ValueListInterface
{
    /**
     * @throws ParseException
     */
    public static function in(ColumnInterface|string ...$columns): static
    {
        return (new static(...$columns))->setOperation(self::OPERATION_IN);
    }

    protected string $operation     = '';

    /**
     *
     * @throws ParseException
     */
    public function __construct(ColumnInterface|string ...$columns)
    {
        parent::__construct();

        $this->childNodes           = [
            self::NODE_COLUMNS      => (new NodeList())->setParentNode($this),
            self::NODE_VALUES       => (new NodeList())->setParentNode($this),
        ];

        foreach ($columns as $column) {
            $this->appendColumn($column);
        }

        $this->setOperation(self::OPERATION_VALUES);
    }

    #[\Override]
    public function getOperation(): string
    {
        return $this->operation;
    }

    #[\Override]
    public function setOperation(string $operation): static
    {
        $this->operation            = $operation;

        return $this;
    }

    #[\Override]
    public function asIn(): static
    {
        return $this->setOperation(self::OPERATION_IN);
    }

    #[\Override]
    public function asNotIn(): static
    {
        return $this->setOperation(self::OPERATION_NOT_IN);
    }

    #[\Override]
    public function isListEmpty(): bool
    {
        return $this->childNodes[self::NODE_COLUMNS]->isEmpty();
    }

    #[\Override]
    public function isListNotEmpty(): bool
    {
        return $this->childNodes[self::NODE_COLUMNS]->isNotEmpty();
    }

    #[\Override]
    public function findValues(string|ColumnInterface ...$columns): array
    {
        $offsets                    = [];
        $results                    = [];

        // define offset by columns
        foreach ($columns as $column) {
            $key                    = $column instanceof ColumnInterface ? $column->getColumnName() : $column;
            $offsets[$key]          = $this->findColumnOffset($column);
            $results[$key]          = [];
        }

        foreach ($this->childNodes[self::NODE_VALUES] as $set) {
            foreach ($offsets as $key => $offset) {
                $childNodes         = $set->getChildNodes();

                if (\array_key_exists($offset, $childNodes)) {
                    $results[$key][] = $childNodes[$offset];
                }
            }
        }

        return $results;
    }

    #[\Override]
    public function walkValues(string|ColumnInterface ...$columns): \Iterator
    {
        $offsets                    = [];

        // define offset by columns
        foreach ($columns as $column) {
            $key                    = $column instanceof ColumnInterface ? $column->getColumnName() : $column;
            $offsets[$key]          = $this->findColumnOffset($column);
        }

        foreach ($this->childNodes[self::NODE_VALUES] as $set) {

            $results                = [];

            foreach ($offsets as $key => $offset) {
                $childNodes         = $set->getChildNodes();

                if (\array_key_exists($offset, $childNodes)) {
                    $results[$key]  = $childNodes[$offset];
                }

                yield [$results, $set];
            }
        }
    }

    #[\Override]
    public function getColumns(): array
    {
        return $this->childNodes[self::NODE_COLUMNS]->getChildNodes();
    }

    /**
     * @throws UnexpectedValueType
     * @throws ParseException
     */
    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof ColumnInterface) {
                $this->appendColumn($node);
            } elseif ($node instanceof NodeList) {
                $this->appendValueList($node);
            } else {
                throw new UnexpectedValueType('node', $node, 'ColumnInterface|NodeList');
            }
        }
    }

    /**
     *
     * @return $this
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

        $this->childNodes[self::NODE_COLUMNS]->addChildNode($column);

        return $this->needTransform();
    }

    /**
     *
     * @return $this
     * @throws
     */
    #[\Override]
    public function defineValues(array $values): static
    {
        if ($this->isListEmpty()) {
            throw new ParseException('Columns should be defined before insert values');
        }

        foreach ($values as $key => $value) {
            if ($value instanceof NodeList) {
                $values[$key]       = $value;
            } elseif (\is_array($value)) {
                $values[$key]       = new NodeList(
                    ...\array_map(static fn($node) => $node instanceof NodeInterface ? $node : new Constant($node), $value)
                );
            }
        }

        $this->childNodes[self::NODE_VALUES] = new NodeList(...$values);

        return $this->needTransform();
    }

    #[\Override]
    public function appendValueList(array|NodeList $valueList): static
    {
        if ($this->isListEmpty()) {
            throw new ParseException('Columns should be defined before insert values');
        }

        $this->childNodes[self::NODE_VALUES]->addChildNode(\is_array($valueList) ? new NodeList(...$valueList) : $valueList);

        return $this;
    }

    /**
     *
     * @return $this
     * @throws ParseException
     */
    #[\Override]
    public function appendColumnToSet(ColumnInterface|string $column, ConstantInterface $value): static
    {
        $this->appendColumn($column);

        foreach ($this->childNodes[self::NODE_VALUES] as $values) {
            $values->addChildNode($value);
        }

        return $this;
    }

    /**
     *
     * @return $this
     * @throws ParseException
     */
    #[\Override]
    public function appendColumnWithGenerator(ColumnInterface|string $column, callable $valueGenerator): static
    {
        $this->appendColumn($column);

        foreach ($this->childNodes as $values) {
            $valueGenerator($values);
        }

        return $this;
    }

    #[\Override]
    public function findColumn(string|ColumnInterface $name): ?ColumnInterface
    {
        foreach ($this->childNodes[self::NODE_COLUMNS] as $column) {
            if ($column->isEqual($name)) {
                return $column;
            }
        }

        return null;
    }

    #[\Override]
    public function findColumnOffset(ColumnInterface|string $name): ?int
    {
        foreach ($this->childNodes[self::NODE_COLUMNS] as $offset => $column) {
            if ($column->isEqual($name)) {
                return $offset;
            }
        }

        return null;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $columns                    = $this->childNodes[self::NODE_COLUMNS]->getChildNodes();
        $values                     = $this->childNodes[self::NODE_VALUES]->getChildNodes();

        if ($columns === []) {
            return '';
        }

        $columns                    = '(' . $this->nodesToAql($columns, ', ', $forResolved) . ')';

        if ($values === []) {
            return $columns;
        }

        $results                    = [];

        foreach ($values as $tuple) {
            $aql                    = $tuple->getAql($forResolved);

            if (!empty($aql)) {
                $results[]          = '(' . $aql . ')';
            }
        }

        if ($results === []) {
            return $columns;
        }

        if ($this->operation === self::OPERATION_VALUES) {
            return $columns . ' VALUES ' . \implode(', ', $results);
        }

        return $columns . ' ' . $this->operation . ' (' . \implode(', ', $results) . ')';
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $columns                    = $this->childNodes[self::NODE_COLUMNS]->getChildNodes();
        $set                        = $this->childNodes[self::NODE_VALUES]->getChildNodes();

        if ($columns === []) {
            return '';
        }

        $strings                    = [];
        $isValues                   = $this->operation === self::OPERATION_VALUES;

        foreach ($columns as $field) {
            $strings[]              = $field->getResult();
        }

        $isSingleColumn             = \count($strings) === 1 && false === $isValues;

        $fields                     = $isSingleColumn ? $strings[0] : '(' . \implode(',', $strings) . ')';

        $strings                    = [];

        foreach ($set as $values) {
            $s                      = [];

            foreach ($values as $value) {
                $s[]                = $isSingleColumn ? $value->getResult() : '(' . $value->getResult() . ')';
            }

            $strings[]              = \implode(',', $s);
        }

        if ($isValues) {
            return $fields . ' VALUES ' . \implode(",\n", $strings);
        }

        return $fields . ' ' . $this->operation . ' (' . \implode(', ', $strings) . ')';
    }
}
