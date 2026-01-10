<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

class SqlString
{
    public static function trim(string $sql): string
    {
        // Remove spaces
        $sql                        = \preg_replace(['/[ ]+/'], ' ', \trim($sql));
        // Remove spaces with \n
        $sql                        = \trim((string) \preg_replace(["/\s+\n/", "/\n\s+/"], "\n", (string) $sql));

        return $sql;
    }

    public static function normalize(string $sql): string
    {
        $sql                        = self::trim($sql);
        // Replace \n with space
        $sql                        = \str_replace("\n", ' ', $sql);
        // Remove duplicate spaces
        $sql                        = \preg_replace(['/[ ]+/'], ' ', $sql);

        return $sql;
    }
}
