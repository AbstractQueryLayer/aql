<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyFloat extends PropertyAbstract
{
    public function __construct(string $name, ?int $size = null, bool $isNullable = false)
    {
        parent::__construct($name, self::T_FLOAT, $isNullable);

        $this->size                 = $size;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = 0.0;
        return $this;
    }
}
