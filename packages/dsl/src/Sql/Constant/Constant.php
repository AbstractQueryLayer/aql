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

        return $this->returnValue($this->formatValue($this->value));
    }

    protected function formatValue(mixed $value): mixed
    {
        return $this->value;
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
