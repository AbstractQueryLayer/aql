<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql;

/**
 * Connection settings of the PostgreSQL server the database tests run against.
 *
 * Each setting comes from an environment variable, and its default matches the server that dev/tools/postgres.sh
 * of the lib repository starts. A test that needs the server fails when it is unreachable, as with MariaDb.
 */
final class PostgresServer
{
    public static function dsn(): string
    {
        return 'pgsql:host=' . self::env('AQL_TEST_PG_HOST', '127.0.0.1') . ';port=' . self::env('AQL_TEST_PG_PORT', '5433')
            . ';dbname=' . self::env('AQL_TEST_PG_NAME', 'aql_test');
    }

    public static function user(): string
    {
        return self::env('AQL_TEST_PG_USER', 'postgres');
    }

    public static function password(): string
    {
        return self::env('AQL_TEST_PG_PASSWORD', 'test');
    }

    /**
     * A plain connection, for preparing and checking data outside the code under test.
     */
    public static function pdo(): \PDO
    {
        return new \PDO(self::dsn(), self::user(), self::password(), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private static function env(string $name, string $default): string
    {
        $value                      = \getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}
