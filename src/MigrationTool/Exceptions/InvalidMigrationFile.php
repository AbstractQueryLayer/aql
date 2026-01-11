<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class InvalidMigrationFile extends MigrationException
{
    protected string $template = 'Invalid migration file: {filePath}. {reason}';

    public function __construct(string $filePath, string $reason)
    {
        parent::__construct([
            'filePath' => $filePath,
            'reason' => $reason,
        ]);
    }
}
