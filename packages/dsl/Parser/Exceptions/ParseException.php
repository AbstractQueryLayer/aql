<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser\Exceptions;

use IfCastle\Exceptions\LoggableException;

class ParseException extends LoggableException
{
    public function __construct(string|array $message, array $data = [])
    {
        if (\is_array($message)) {
            parent::__construct($message);
            return;
        }

        $data['message']            = $message;

        parent::__construct($data);
    }
}
