<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

/**
 * @see https://dev.mysql.com/doc/refman/9.1/en/set-operations.html
 */
enum UnionEnum: string
{
    public const string ALL         = 'ALL';
    public const string DISTINCT    = 'DISTINCT';

    case UNION                      = 'UNION';
    case INTERSECT                  = 'INTERSECT';
    case EXCEPT                     = 'EXCEPT';
}
