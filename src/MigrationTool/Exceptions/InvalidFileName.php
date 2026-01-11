<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class InvalidFileName extends MigrationException
{
    protected string $template = 'Invalid migration file name format: {fileName}';

    public function __construct(string $fileName)
    {
        parent::__construct([
            'fileName' => $fileName,
        ]);
    }
}
