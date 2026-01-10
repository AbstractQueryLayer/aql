<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyEnum extends PropertyAbstract implements PropertyEnumInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(string $name, protected array $variants, bool $isNullable = false, protected bool $isVariantsVirtual = false)
    {
        parent::__construct($name, self::T_ENUM, $isNullable);
    }

    #[\Override]
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function setVariants(array $variants): static
    {
        $this->variants             = $variants;

        return $this;
    }

    #[\Override]
    public function isVariantsVirtual(): bool
    {
        return $this->isVariantsVirtual;
    }

    #[\Override]
    public function setVariantsVirtual(bool $isVariantsVirtual): static
    {
        $this->isVariantsVirtual    = $isVariantsVirtual;

        return $this;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        if ($this->variants === []) {
            return $this;
        }

        $this->defaultValue         = $this->variants[0];
        return $this;
    }
}
