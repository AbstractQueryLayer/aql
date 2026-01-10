<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class PropertyNotFound extends QueryException
{
    protected string $template      = 'The entity {entity} property {property} is not found';

    public function __construct(string $entityName, string $property)
    {
        parent::__construct([
            'entity'                => $entityName,
            'property'              => $property,
        ]);
    }

}
