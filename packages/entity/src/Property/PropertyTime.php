<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyTime extends PropertyAbstract
{
    /**
     * @inheritDoc
     */
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_TIME, $isNullable);
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = '00:00:00';
        return $this;
    }
}
