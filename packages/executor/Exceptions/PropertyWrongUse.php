<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class PropertyWrongUse extends QueryException
{
    protected string $template      = 'The property {entity}.{property} wrong use. {reason}';

    public function __construct(string $entityName, string $property, string $reason = '')
    {
        parent::__construct([
            'entity'                => $entityName,
            'property'              => $property,
            'reason'                => $reason,
        ]);
    }
}
