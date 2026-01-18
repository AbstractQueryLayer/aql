<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Parameter;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\Exceptions\UnexpectedMethodMode;

class Parameter extends Constant implements ParameterInterface
{
    public static function fromValue(string|int|float|bool|null|array|NodeInterface $value): NodeInterface
    {
        if ($value instanceof NodeInterface) {
            return $value;
        }

        return new static(isValueList: \is_array($value))->setParameterValue($value);
    }

    protected bool $isResolved      = false;

    protected mixed $setter         = null;

    protected \WeakReference|null $reference = null;

    public function __construct(protected ?string $parameterName = null, ?string $parameterType = null, protected bool $isValueList = false)
    {
        parent::__construct(null, $parameterType);
    }

    #[\Override]
    public function isValueList(): bool
    {
        if (($reference = $this->reference?->get()) !== null) {
            return $reference->isValueList();
        }

        return $this->isValueList;
    }

    /**
     * @throws UnexpectedMethodMode
     */
    #[\Override]
    public function getConstantValue(): mixed
    {
        if (($reference = $this->reference?->get()) !== null) {
            return $reference->getConstantValue();
        }

        if (false === $this->isResolved) {
            throw new UnexpectedMethodMode(__METHOD__, 'Try to get value before setup');
        }

        return $this->value;
    }

    #[\Override]
    public function getParameterName(): string
    {
        return $this->parameterName ?? '';
    }

    #[\Override]
    public function setParameterValue(mixed $value): static
    {
        $this->value                = $value;
        $this->isResolved           = true;
        $this->reference            = null;

        if ($this->setter !== null) {
            ($this->setter)($this);
        }

        return $this;
    }

    /**
     * @throws UnexpectedMethodMode
     */
    #[\Override]
    public function defineParameterSetter(callable $setter): static
    {
        if ($this->setter !== null) {
            throw new UnexpectedMethodMode(__METHOD__, 'Try to setup setter after value setup');
        }

        $this->setter               = $setter;
        return $this;
    }

    #[\Override]
    public function referenceTo(ParameterInterface $parameter): static
    {
        if ($this === $parameter) {
            return $this;
        }

        $this->reference            = \WeakReference::create($parameter);
        return $this;
    }

    #[\Override]
    public function asPlaceholder(?string $placeholder = null): static
    {
        if ($placeholder === null && $this->parameterName !== null) {
            $placeholder            = ':' . $this->parameterName;
        }

        return parent::asPlaceholder($placeholder);
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '{' . ($this->parameterName ?? '?') . '}';
    }

    #[\Override]
    public function isParameterDefault(): bool
    {
        return $this->parameterName === '%';
    }

    #[\Override]
    public function isParameterResolved(): bool
    {
        if (($reference = $this->reference?->get()) !== null) {
            return $reference->isResolved();
        }

        return $this->isResolved;
    }

    #[\Override]
    public function dispose(): void
    {
        $this->value                = null;
        $this->setter               = null;
        $this->reference            = null;
        parent::dispose();
    }
}
