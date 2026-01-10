<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

use IfCastle\TypeDefinitions\DefinitionInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Map
{
    public function __construct(
        public string $property             = '',
        public bool $isPrimaryKey           = false,
        public bool $isHidden               = false,
        public bool $isReadOnly             = false,
        public string $entityName           = '',
        public ?string $encodeKey           = null,
        public ?string $hydratorClass       = null,
        public ?DefinitionInterface $definition = null,
    ) {}
}
