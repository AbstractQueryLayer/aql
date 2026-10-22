<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

/**
 * The prepared statement cases with server-side prepares: MariaDB receives the statement and its values apart,
 * where the parent case lets pdo_mysql write the values into the SQL on the client.
 */
final class AqlNativePreparedStatementMariaDbTest extends AqlPreparedStatementMariaDbTest
{
    #[\Override]
    protected function pdoOptions(): array
    {
        return [\PDO::ATTR_EMULATE_PREPARES => false];
    }
}
