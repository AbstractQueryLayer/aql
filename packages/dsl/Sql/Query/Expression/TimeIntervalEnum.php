<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

enum TimeIntervalEnum: string
{
    case SECOND                     = 'SECOND';
    case MINUTE                     = 'MINUTE';
    case HOUR                       = 'HOUR';
    case DAY                        = 'DAY';
    case WEEK                       = 'WEEK';
    case MONTH                      = 'MONTH';
    case QUARTER                    = 'QUARTER';
    case YEAR                       = 'YEAR';
}
