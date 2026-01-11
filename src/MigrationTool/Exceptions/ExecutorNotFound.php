<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class ExecutorNotFound extends MigrationException
{
    protected string $template = 'No executor found for migration type: {type}';

    public function __construct(string $type)
    {
        parent::__construct([
            'type' => $type,
        ]);
    }
}
