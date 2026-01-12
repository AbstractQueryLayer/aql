<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyEnum extends PropertyAbstract implements PropertyEnumInterface
{
    protected array $variants;
    protected ?string $enumClass = null;

    /**
     * @param string $name Property name
     * @param string|array $variants PHP enum class name or array of variants
     * @param bool $isNullable
     * @param bool $isVariantsVirtual
     */
    public function __construct(
        string $name,
        string|array $variants,
        bool $isNullable = false,
        protected bool $isVariantsVirtual = false
    ) {
        if (is_string($variants)) {
            if (!enum_exists($variants)) {
                throw new \InvalidArgumentException("Enum class does not exist: {$variants}");
            }

            $this->enumClass = $variants;
            $this->variants = $this->extractVariantsFromEnum($variants);
        } else {
            $this->variants = $variants;
        }

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
        $this->variants = $variants;

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
        $this->isVariantsVirtual = $isVariantsVirtual;

        return $this;
    }

    /**
     * Get the PHP enum class name if this property is backed by an enum.
     */
    public function getEnumClass(): ?string
    {
        return $this->enumClass;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        if ($this->variants === []) {
            return $this;
        }

        $this->defaultValue = $this->variants[0];
        return $this;
    }

    /**
     * Extract variant values from PHP enum.
     *
     * @param class-string $enumClass
     * @return array
     */
    private function extractVariantsFromEnum(string $enumClass): array
    {
        $reflection = new \ReflectionEnum($enumClass);
        $cases = $reflection->getCases();
        $variants = [];

        foreach ($cases as $case) {
            // Check if this is a backed enum
            if ($reflection->isBacked()) {
                /** @var \BackedEnum $enumInstance */
                $enumInstance = $case->getValue();
                $variants[] = $enumInstance->value;
            } else {
                // Unit enum - use case name
                $variants[] = $case->getName();
            }
        }

        return $variants;
    }
}
