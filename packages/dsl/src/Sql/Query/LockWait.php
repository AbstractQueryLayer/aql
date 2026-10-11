<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

/**
 * What a locking read does with a row another transaction has locked. Without one, it waits for the lock up to the
 * server's lock-wait timeout.
 */
enum LockWait: string
{
    /** The locked row is left out of the result; the read neither waits nor fails. */
    case SKIP_LOCKED                = 'SKIP LOCKED';

    /** The read fails at once instead of waiting. */
    case NOWAIT                     = 'NOWAIT';
}
