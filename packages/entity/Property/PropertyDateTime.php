<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyDateTime extends PropertyAbstract
{
    /**
     * @inheritDoc
     */
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_DATETIME, $isNullable);
    }

    #[\Override]
    public function withDefaultValue(): static
    {
        $this->defaultValue         = '0000-00-00 00:00:00';
        return $this;
    }
}
