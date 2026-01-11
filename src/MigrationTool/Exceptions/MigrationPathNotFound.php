<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class MigrationPathNotFound extends MigrationException
{
    protected string $template = 'Migration base path does not exist: {basePath}';

    public function __construct(string $basePath)
    {
        parent::__construct([
            'basePath' => $basePath,
        ]);
    }
}
