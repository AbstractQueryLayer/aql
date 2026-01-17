<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ColumnListInterface;

/**
 * Class LROperator.
 *
 * Left - right expression:
 *
 * leftExpression operator rightExpression
 * example: var = 3
 *
 */
class LROperation extends NodeAbstract implements LROperationInterface
{
    public function __construct(NodeInterface|string $left, protected string $operation, NodeInterface $right)
    {
        parent::__construct();

        $this->childNodes[self::LEFT] = \is_string($left) ? new Column($left) : $left;
        $this->childNodes[self::RIGHT] = $right;

        $this->childNodes[self::LEFT]->setParentNode($this);
        $this->childNodes[self::RIGHT]->setParentNode($this);
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->childNodes[self::LEFT]->getAql($forResolved) . ' ' . $this->operation . ' ' . $this->childNodes[self::RIGHT]->getAql(
            $forResolved
        );
    }

    #[\Override]
    public function isCompositeComparison(): bool
    {
        return $this->childNodes[self::LEFT] instanceof ColumnListInterface;
    }

    #[\Override]
    public function getLeftNode(): ?NodeInterface
    {
        return $this->childNodes[self::LEFT] ?? null;
    }

    #[\Override]
    public function getRightNode(): ?NodeInterface
    {
        return $this->childNodes[self::RIGHT] ?? null;
    }

    #[\Override]
    public function getLeftKey(): ?ColumnInterface
    {
        if ($this->childNodes[self::LEFT] instanceof ColumnInterface && $this->childNodes[self::LEFT]->isForeign() === false) {
            return $this->childNodes[self::LEFT];
        }

        if ($this->childNodes[self::RIGHT] instanceof ColumnInterface && $this->childNodes[self::RIGHT]->isForeign() === false) {
            return $this->childNodes[self::RIGHT];
        }

        return null;
    }

    #[\Override]
    public function getForeignKey(): ?ColumnInterface
    {
        if ($this->childNodes[self::LEFT] instanceof ColumnInterface && $this->childNodes[self::LEFT]->isForeign()) {
            return $this->childNodes[self::LEFT];
        }

        if ($this->childNodes[self::RIGHT] instanceof ColumnInterface && $this->childNodes[self::RIGHT]->isForeign()) {
            return $this->childNodes[self::RIGHT];
        }

        return null;
    }

    #[\Override]
    public function getLeftKeys(): array
    {
        if (false === $this->isCompositeComparison()) {
            $leftKey            = $this->getLeftKey();

            if ($leftKey !== null) {
                return [$leftKey];
            }

            return [];
        }

        $leftKeys               = [];

        if ($this->childNodes[self::LEFT] instanceof ColumnListInterface) {
            foreach ($this->childNodes[self::LEFT]->getColumns() as $column) {
                if ($column->isForeign() === false) {
                    $leftKeys[] = $column;
                }
            }
        }

        if ($this->childNodes[self::RIGHT] instanceof ColumnListInterface) {
            foreach ($this->childNodes[self::RIGHT]->getColumns() as $column) {
                if ($column->isForeign() === false) {
                    $leftKeys[] = $column;
                }
            }
        }

        return $leftKeys;
    }

    #[\Override]
    public function getForeignKeys(): array
    {
        if (false === $this->isCompositeComparison()) {
            $foreignKey            = $this->getForeignKey();

            if ($foreignKey !== null) {
                return [$foreignKey];
            }

            return [];
        }

        $foreignKeys               = [];

        if ($this->childNodes[self::LEFT] instanceof ColumnListInterface) {
            foreach ($this->childNodes[self::LEFT]->getColumns() as $column) {
                if ($column->isForeign()) {
                    $foreignKeys[] = $column;
                }
            }
        }

        if ($this->childNodes[self::RIGHT] instanceof ColumnListInterface) {
            foreach ($this->childNodes[self::RIGHT]->getColumns() as $column) {
                if ($column->isForeign()) {
                    $foreignKeys[] = $column;
                }
            }
        }

        return $foreignKeys;
    }

    #[\Override]
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Return Value for right side or null
     * Return not null if rightExpression is ConstantAbstract.
     */
    #[\Override]
    public function getConstantValue(): mixed
    {
        if ($this->childNodes[self::RIGHT] instanceof ConstantInterface) {
            return $this->childNodes[self::RIGHT]->getConstantValue();
        }

        return null;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $left                       = $this->childNodes[self::LEFT]->getResult();
        $right                      = $this->childNodes[self::RIGHT]->getResult();

        if ($left === '' || $right === '') {
            return '';
        }

        return $left . ' ' . $this->operation . ' ' . $right;
    }
}
