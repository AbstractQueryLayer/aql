<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Key;

class Key implements KeyInterface
{
    /**
     * @var string[]
     */
    protected array $columns;

    protected bool $isPrimary       = false;

    protected bool $isUnique        = false;

    protected bool $isFulltext      = false;

    public function __construct(string ...$columns)
    {
        $this->columns              = $columns;
    }

    #[\Override]
    public function getKeyName(): string
    {
        return \implode('_', $this->columns);
    }

    #[\Override]
    public function getKeyColumns(): array
    {
        return $this->columns;
    }

    #[\Override]
    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    #[\Override]
    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    #[\Override]
    public function isFulltext(): bool
    {
        return $this->isFulltext;
    }

    #[\Override]
    public function isKeySimple(): bool
    {
        return \count($this->columns) === 1;
    }

    #[\Override]
    public function isKeyComplex(): bool
    {
        return \count($this->columns) > 1;
    }

    #[\Override]
    public function isEquals(KeyInterface $key): bool
    {
        foreach ($this->columns as $column) {
            if (false === \in_array($column, $key->getKeyColumns(), true)) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function asPrimary(): static
    {
        $this->isPrimary            = true;
        return $this;
    }

    #[\Override]
    public function asUnique(): static
    {
        $this->isUnique             = true;
        return $this;
    }

    #[\Override]
    public function asFulltext(): static
    {
        $this->isFulltext           = true;
        return $this;
    }
}
