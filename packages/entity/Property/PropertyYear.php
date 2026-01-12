<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyYear extends PropertyAbstract
{
    /**
     * @inheritDoc
     */
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_YEAR, $isNullable);
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = '0000';
        return $this;
    }
}
