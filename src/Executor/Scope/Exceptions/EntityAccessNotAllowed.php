<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope\Exceptions;

use IfCastle\Exceptions\ClientException;

class EntityAccessNotAllowed extends ClientException
{
    public function __construct(string $entity, string $what, string $value, string $scopeName)
    {
        parent::__construct(
            'The {what} {value} is not allowed for {entity} within the scope {scopeName}',
            ['what' => $what, 'entity' => $entity, 'value' => $value, 'scopeName' => $scopeName]
        );
    }
}
