<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

enum MigrationStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ROLLBACK = 'rollback';
}
