<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope\Exceptions;

use IfCastle\Exceptions\ClientException;

class FunctionAccessNotAllowed extends ClientException
{
    public function __construct(string $function, string $scopeName)
    {
        parent::__construct(
            'The function {function} is not allowed within the scope {scopeName}',
            ['function' => $function, 'scopeName' => $scopeName]
        );
    }
}
