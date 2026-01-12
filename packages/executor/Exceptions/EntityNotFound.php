<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class EntityNotFound extends QueryException
{
    protected string $template      = 'The entity {entity} is not found';

    public function __construct(string $entityName)
    {
        parent::__construct(['entity' => $entityName]);
    }
}
