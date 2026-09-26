<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\PostgreSql\Storage\PostgreSql;
use IfCastle\AQL\SQLite\Storage\SQLite;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\QueryException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use PHPUnit\Framework\TestCase;

/**
 * Error mapping of the PDO storages that need no server; MySQL errors are checked on MariaDB in MySqlErrorsTest.
 */
class StorageErrorsTest extends TestCase
{
    public function testSqliteUniqueViolationRaisesDuplicateKeysException(): void
    {
        $exception                  = $this->sqliteFailureOf('INSERT INTO t (id, name) VALUES (1, 2)');

        $this->assertInstanceOf(DuplicateKeysException::class, $exception);
    }

    public function testSqliteOtherConstraintIsAPlainQueryError(): void
    {
        $exception                  = $this->sqliteFailureOf('INSERT INTO t (id, name) VALUES (2, NULL)');

        $this->assertSame(QueryException::class, $exception::class);
    }

    public function testPostgreSqlStorageLoads(): void
    {
        // The PDO constructor runs on the first query, so no server is needed here.
        $this->assertInstanceOf(PDOAbstract::class, new PostgreSql(['dsn' => 'pgsql:host=127.0.0.1']));
    }

    private function sqliteFailureOf(string $sql): StorageException
    {
        $sqLite                     = new SQLite(['dsn' => ':memory:']);
        $sqLite->executeSql('CREATE TABLE t (id INTEGER PRIMARY KEY, name INTEGER NOT NULL)');
        $sqLite->executeSql('INSERT INTO t (id, name) VALUES (1, 1)');

        try {
            $sqLite->executeSql($sql);
        } catch (StorageException $exception) {
            return $exception;
        }

        $this->fail('The query did not fail: ' . $sql);
    }
}
