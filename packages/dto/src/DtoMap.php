<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/**
 * Using this attribute on a DTO class will allow the DTO to be mapped to an entity.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class DtoMap
{
    public function __construct(
        public string $entityName,
        public string $scope = '',
        /**
         * @var array<string, Map>
         */
        public array $properties = []
    ) {}
}
