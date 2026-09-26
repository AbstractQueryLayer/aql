<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\SQLite\Storage\SQLite;
use IfCastle\DI\Exceptions\ConfigException;
use PHPUnit\Framework\TestCase;

class PersistentConnectionTest extends TestCase
{
    public function testPersistentConnectionsAreRefused(): void
    {
        $this->expectException(ConfigException::class);

        new SQLite(['dsn' => ':memory:', 'options' => [\PDO::ATTR_PERSISTENT => true]]);
    }

    public function testNoPersistentConnectionByDefault(): void
    {
        $sqLite                     = new class (['dsn' => ':memory:']) extends SQLite {
            /**
             * @return array<int, mixed>
             */
            public function options(): array
            {
                return $this->options;
            }
        };

        $this->assertArrayNotHasKey(\PDO::ATTR_PERSISTENT, $sqLite->options());
    }
}
