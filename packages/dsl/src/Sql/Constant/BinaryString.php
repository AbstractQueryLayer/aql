<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

/**
 * Bytes that a storage writes into SQL as a binary literal instead of a quoted text string.
 *
 * A text literal is checked against the connection charset by some databases (PostgreSQL) and cannot hold a NUL
 * byte in others (PDO::quote of SQLite), so raw bytes need the binary form of the dialect.
 */
final readonly class BinaryString
{
    public function __construct(public string $bytes) {}

    /**
     * The SQL-standard hexadecimal literal X'…', read as bytes by MySQL, MariaDB and SQLite.
     */
    public function toHexLiteral(): string
    {
        return 'X\'' . \bin2hex($this->bytes) . '\'';
    }
}
