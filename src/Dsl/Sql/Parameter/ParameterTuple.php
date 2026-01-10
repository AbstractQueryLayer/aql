<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Parameter;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantTuple;

class ParameterTuple extends ConstantTuple implements ParameterTupleInterface
{
    protected bool $isResolved      = false;

    public function __construct(protected array $columns = [], ?array $types = null)
    {
        parent::__construct([], $types);
    }

    #[\Override]
    public function getParameterName(): string
    {
        return \implode('_', $this->tuple);
    }

    #[\Override]
    public function isParameterDefault(): bool
    {
        return false;
    }

    #[\Override]
    public function isParameterResolved(): bool
    {
        return $this->isResolved;
    }

    #[\Override]
    public function getTupleColumns(): array
    {
        return $this->columns;
    }

    #[\Override]
    public function setParameterValue(mixed $value): static
    {
        $this->tuple                = $value;
        $this->isResolved           = true;
        return $this;
    }

    /**
     * Returns expression: {column1, column2}.
     *
     *
     */
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '{' . \implode(', ', \array_keys($this->columns)) . '}';
    }
}
