<?php

declare(strict_types=1);

namespace IfCastle\AQL\TestCases;

use IfCastle\AQL\SQLite\Storage\SQLite;

class MemorySQLiteStorage extends SQLite
{
    public function __construct()
    {
        parent::__construct(['dsn' => ':memory:']);
    }
}
