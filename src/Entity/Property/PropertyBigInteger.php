<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyBigInteger extends PropertyAbstract
{
    public function __construct(string $name, ?int $size = null, bool $isNullable = false)
    {
        parent::__construct($name, self::T_BIG_INT, $isNullable);

        $this->size                 = $size;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = 0;
        return $this;
    }
}
