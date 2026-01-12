<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope\Exceptions;

use IfCastle\Exceptions\ClientException;

class EntityIsNotFound extends ClientException
{
    public function __construct(string $entityName, string $scopeName)
    {
        parent::__construct(
            'The entity {entity} is not found within the scope {scopeName}',
            ['entity' => $entityName, 'scopeName' => $scopeName]
        );
    }
}
