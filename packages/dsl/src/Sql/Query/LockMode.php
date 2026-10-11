<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

/**
 * How a locking read protects the rows it returns.
 */
enum LockMode: string
{
    /** An exclusive lock: other transactions can neither lock nor change the rows until this one ends. */
    case FOR_UPDATE                 = 'FOR UPDATE';

    /** A shared lock: other transactions can read the rows and take a shared lock, but cannot change them. */
    case SHARE                      = 'LOCK IN SHARE MODE';
}
