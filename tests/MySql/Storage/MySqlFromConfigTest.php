<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Storage;

use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\BaseTestCase;
use IfCastle\AQL\Storage\StorageCollectionByConfig;

class MySqlFromConfigTest extends BaseTestCase
{
    public function testStorageCollectionBuildsMySqlFromItsSection(): void
    {
        $this->getConfigMutable()->setSection('storages', [
            'main'                  => [
                'class'             => MySql::class,
                'dsn'               => MariaDb::dsn(),
                'username'          => MariaDb::user(),
                'password'          => MariaDb::password(),
                // Not the driver's default ATTR_PERSISTENT: TrueAsync crashes at process shutdown
                // when it closes a persistent pdo_mysql connection that served a coroutine.
                'options'           => [\PDO::ATTR_PERSISTENT => false],
            ],
        ]);

        $collection                 = new StorageCollectionByConfig();
        $collection->resolveDependencies($this->getDiContainer());

        $storage                    = $collection->findStorage('main');

        $this->assertInstanceOf(MySql::class, $storage);
        $this->assertSame('main', $storage->getStorageName());
        $this->assertSame([['answer' => 42]], $storage->executeSql('SELECT 42 AS answer')->toArray());

        $collection->dispose();
    }
}
