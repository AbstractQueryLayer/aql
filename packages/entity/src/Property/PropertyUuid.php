<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertyUuid extends PropertyAbstract
{
    /**
     * @inheritDoc
     */
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_UUID, $isNullable);
    }
}
