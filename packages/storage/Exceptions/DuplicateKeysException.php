<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage\Exceptions;

class DuplicateKeysException extends StorageException
{
    public function __construct(string $driverMessage, string $query = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            [
                'message'               => $driverMessage,
                'query'                 => $query,
            ],
            0,
            $previous
        );
    }
}
