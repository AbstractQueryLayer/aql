<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\Exceptions;

class InvalidExecutorType extends MigrationException
{
    protected string $template = '{executorClass} can only execute {expectedType} migrations, got {actualType}';

    public function __construct(string $executorClass, string $expectedType, string $actualType)
    {
        parent::__construct([
            'executorClass' => $executorClass,
            'expectedType' => $expectedType,
            'actualType' => $actualType,
        ]);
    }
}
