<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage\Exceptions;

/**
 * Class QueryException.
 */
class QueryException extends StorageException
{
    protected string $template      = 'Query error: \'{error}\' in SQL: \'{sql}\'';

    /**
     * @inheritDoc
     */
    public function __construct(string $error, string $sql, ?\Throwable $previous = null)
    {
        $data                       = ['error' => $error, 'sql' => $sql, 'aql' => '', 'previous' => $previous];

        parent::__construct($data);
    }
}
