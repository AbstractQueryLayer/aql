<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class FunctionParameterWrong extends QueryException
{
    protected string $template      = 'The function parameter {entity}.{function}.{parameter} is wrong. {reason}';

    public function __construct(string $entityName, string $function, string $parameter, string $reason = '')
    {
        parent::__construct([
            'entity'                => $entityName,
            'function'              => $function,
            'parameter'             => $parameter,
            'reason'                => $reason,
        ]);
    }
}
