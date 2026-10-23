<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

enum ReadonlyStateMode
{
    case SHARED;
    case EXCLUSIVE;

    /** An alias names a case under another name; the case itself is still SHARED. */
    public const ALIAS              = self::SHARED;

    public const NOT_A_CASE         = 'shared';
}
