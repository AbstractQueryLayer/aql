<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage\Exceptions;

/**
 * Error that can be resolved by retrying the Query.
 * You must be careful when using this type of exception.
 *
 */
class RecoverableException extends StorageException
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
