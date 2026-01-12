<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

class FunctionWrongUse extends QueryException
{
    protected string $template      = 'The function {entity}.{function} wrong use. {reason}';

    public function __construct(string $entityName, string $function, string $reason = '')
    {
        parent::__construct([
            'entity'                => $entityName,
            'function'              => $function,
            'reason'                => $reason,
        ]);
    }
}
