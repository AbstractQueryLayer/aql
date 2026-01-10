<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\TypeDefinitions\TypeObject;

class ConstantTuple extends NodeAbstract implements ConstantTupleInterface
{
    use ConstantTrait;

    protected bool $isVariable      = false;

    protected string|null $placeholder = null;

    public function __construct(protected array $tuple = [], protected ?array $types = null)
    {
        parent::__construct();
    }

    #[\Override]
    public function getConstantType(): string
    {
        return TypeObject::class;
    }

    #[\Override]
    public function getConstantValue(): array
    {
        return $this->tuple;
    }

    #[\Override]
    public function isValueList(): bool
    {
        return false;
    }

    #[\Override]
    public function isVariable(): bool
    {
        return $this->isVariable;
    }

    #[\Override]
    public function asVariable(): static
    {
        $this->isVariable           = true;
        return $this;
    }

    #[\Override]
    public function getTupleColumns(): array
    {
        return \array_keys($this->tuple);
    }

    #[\Override]
    public function getTupleColumnTypes(): ?array
    {
        return $this->types;
    }

    #[\Override]
    public function asPlaceholder(?string $placeholder = null): static
    {
        $this->placeholder          = $placeholder ?? '?';
        return $this;
    }

    #[\Override]
    public function getPlaceholder(): string|null
    {
        return $this->placeholder;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->isVariable) {
            return '(' . \implode(', ', \array_pad([], \count($this->tuple), '?')) . ')';
        }

        $result                     = [];

        foreach ($this->tuple as $value) {
            $result[]               = $this->constantToAQL($value);
        }

        return \implode(', ', $result);
    }
}
