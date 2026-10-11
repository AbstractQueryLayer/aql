<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;

class Constant extends NodeAbstract implements ConstantInterface
{
    use ConstantTrait;
    protected bool $isVariable      = false;

    protected string|null $placeholder = null;

    protected bool $isBinary        = false;

    public function __construct(protected mixed $value = null, protected ?string $type = null)
    {
        parent::__construct();
    }

    #[\Override]
    public function getConstantType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getConstantValue(): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function isValueList(): bool
    {
        return \is_array($this->value);
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->isVariable) {
            return '?';
        }

        return $this->constantToAQL($this->value);
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

    /**
     * @throws TransformationException
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->placeholder !== null) {
            return $this->placeholder;
        }

        // Not $this->value: a repeated parameter holds its value in the parameter it references.
        return $this->returnValue($this->formatValue($this->getConstantValue()));
    }

    /**
     * Makes the constant render its strings, or the strings of its value list, as binary literals.
     */
    public function asBinary(): static
    {
        $this->isBinary             = true;

        return $this;
    }

    public function isBinary(): bool
    {
        return $this->isBinary;
    }

    protected function formatValue(mixed $value): mixed
    {
        if (false === $this->isBinary) {
            return $value;
        }

        if (\is_array($value)) {
            return \array_map(static fn(mixed $item): mixed => \is_string($item) ? new BinaryString($item) : $item, $value);
        }

        return \is_string($value) ? new BinaryString($value) : $value;
    }

    /**
     * @throws TransformationException
     */
    protected function returnValue(mixed $value): string
    {
        if (\is_array($value)) {
            return $this->arrayToSql($value);
        }

        return $this->valueToSql($value);
    }
}
