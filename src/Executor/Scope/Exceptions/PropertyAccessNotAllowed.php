<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope\Exceptions;

use IfCastle\Exceptions\ClientException;

class PropertyAccessNotAllowed extends ClientException
{
    public function __construct(string $entity, string $property, string $scopeName)
    {
        parent::__construct(
            'The {what} {value} is not allowed for {entity} within the scope {scopeName}',
            ['what' => 'property', 'entity' => $entity, 'value' => $property, 'scopeName' => $scopeName]
        );
    }
}
