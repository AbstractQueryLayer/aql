<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

class PropertySlug extends PropertyString
{
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, $isNullable);

        $this->pattern              = '/^[A-Z_][A-Z-0-9_]+$/i';
    }
}
