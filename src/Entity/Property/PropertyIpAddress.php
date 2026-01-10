<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyIpAddress extends PropertyAbstract
{
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_STRING, $isNullable);
        $this->maxLength            = 32;
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = '';
        return $this;
    }
}
