<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\Exceptions\UnexpectedMethodMode;

class TupleColumn extends NodeAbstract implements TupleColumnInterface
{
    public const string NODE_EXPRESSION = 'expression';

    public function __construct(NodeInterface $expression, protected ?string $alias = null)
    {
        parent::__construct();
        $this->childNodes[self::NODE_EXPRESSION] = $expression->setParentNode($this);
    }

    #[\Override]
    public function getExpression(): NodeInterface
    {
        return $this->childNodes[self::NODE_EXPRESSION];
    }

    #[\Override]
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    #[\Override]
    public function getAliasOrColumnNameOrNull(): ?string
    {
        if ($this->alias !== null && $this->alias !== '' && $this->alias !== '0') {
            return $this->alias;
        }

        $expression                 = $this->getExpression();

        if ($expression instanceof ColumnInterface) {
            return $expression->getColumnName();
        }

        return null;
    }

    /**
     * @throws UnexpectedMethodMode
     */
    #[\Override]
    public function getAliasOrColumnName(): string
    {
        return $this->getAliasOrColumnNameOrNull() ?? throw new UnexpectedMethodMode(__METHOD__, 'alias undefined');
    }

    #[\Override]
    public function setAliasIfUndefined(string $alias): static
    {
        if ($this->alias === null || $this->alias === '') {
            $this->alias            = $alias;
            return $this;
        }

        return $this;
    }

    #[\Override]
    public function markAsPlaceholder(): static
    {
        $expression                 = $this->getExpression();

        if ($expression instanceof ColumnInterface) {
            $expression->markAsPlaceholder();
            $this->setAliasIfUndefined($expression->getColumnName());
        }

        return $this;
    }

    #[\Override]
    public function isColumnEqual(TupleColumnInterface $column): bool
    {
        $thisColumn                 = $this->getExpression();
        $otherColumn                = $column->getExpression();

        if ($thisColumn instanceof ColumnInterface && $otherColumn instanceof ColumnInterface) {
            return $thisColumn->isEqual($otherColumn);
        }

        return false;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = $this->childNodes[self::NODE_EXPRESSION]->getAql($forResolved);

        if ($this->alias !== null && $this->alias !== '' && $this->alias !== '0') {
            return $aql . ' as "' . $this->alias . '"';
        }

        return $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->childNodes[self::NODE_EXPRESSION]->getResult();

        if ($this->alias !== null && $this->alias !== '' && $this->alias !== '0') {
            return $result . ' as ' . $this->escape($this->alias);
        }

        return $result;
    }
}
