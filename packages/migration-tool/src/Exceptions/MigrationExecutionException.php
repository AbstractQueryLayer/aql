<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class MigrationExecutionException extends MigrationException
{
    protected string $template = 'Migration execution failed: {taskName} v{version}. {reason}';

    public function __construct(string $taskName, int $version, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct([
            'taskName' => $taskName,
            'version' => $version,
            'reason' => $reason,
        ], 0, $previous);
    }
}
