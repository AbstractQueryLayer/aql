<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class RollbackCodeMissing extends MigrationException
{
    protected string $template = 'Rollback code is missing for migration: {taskName} v{version}';

    public function __construct(string $taskName, int $version)
    {
        parent::__construct([
            'taskName' => $taskName,
            'version' => $version,
        ]);
    }
}
