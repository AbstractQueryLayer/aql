<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql;

use PHPUnit\Framework\TestCase;

class MariaDbServerTest extends TestCase
{
    public function testTheTestServerIsMariaDb(): void
    {
        $version                    = (string) MariaDb::pdo()->query('SELECT VERSION()')->fetchColumn();

        $this->assertStringContainsString('MariaDB', $version);
    }
}
