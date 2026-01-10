<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyString extends PropertyAbstract
{
    public function __construct(string $name, bool $isNullable = false, $maxLength = null)
    {
        parent::__construct($name, self::T_STRING, $isNullable);
        $this->maxLength            = $maxLength;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = '';
        return $this;
    }
}
