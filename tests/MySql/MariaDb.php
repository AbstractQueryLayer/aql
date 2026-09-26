<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql;

/**
 * Connection settings of the MariaDB server the database tests run against.
 *
 * Each setting comes from an environment variable, and its default matches the server that
 * dev/tools/mariadb.sh of the lib repository starts. A test that needs the server fails when it is
 * unreachable: a skipped database test would hide that nothing was checked.
 */
final class MariaDb
{
    public static function host(): string
    {
        return self::env('AQL_TEST_DB_HOST', '127.0.0.1');
    }

    public static function port(): int
    {
        return (int) self::env('AQL_TEST_DB_PORT', '3307');
    }

    public static function database(): string
    {
        return self::env('AQL_TEST_DB_NAME', 'aql_test');
    }

    public static function user(): string
    {
        return self::env('AQL_TEST_DB_USER', 'root');
    }

    public static function password(): string
    {
        return self::env('AQL_TEST_DB_PASSWORD', 'test');
    }

    public static function dsn(): string
    {
        return 'mysql:host=' . self::host() . ';port=' . self::port() . ';dbname=' . self::database() . ';charset=utf8mb4';
    }

    /**
     * A plain connection with no pool, for preparing and checking data outside the code under test.
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
