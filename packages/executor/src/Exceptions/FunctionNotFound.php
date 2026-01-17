<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class FunctionNotFound extends QueryException
{
    protected string $template      = 'The function {entity}.{function} is not found';

    public function __construct(string $function, string $entityName = 'global')
    {
        parent::__construct([
            'function'              => $function,
            'entity'                => $entityName,
        ]);
    }
}
