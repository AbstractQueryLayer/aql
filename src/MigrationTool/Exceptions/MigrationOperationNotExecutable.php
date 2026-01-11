<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class MigrationOperationNotExecutable extends MigrationException
{
    protected string $template = 'Migration operation cannot be executed directly. {reason}';

    public function __construct(string $reason)
    {
        parent::__construct([
            'reason' => $reason,
        ]);
    }
}
