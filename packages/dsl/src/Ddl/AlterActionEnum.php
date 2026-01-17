<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

enum AlterActionEnum: string
{
    case ADD                        = 'ADD';
    case DROP                       = 'DROP';
    case MODIFY                     = 'MODIFY';
    case CHANGE                     = 'CHANGE';
    case RENAME                     = 'RENAME';
    case SET                        = 'SET';
    case ORDER                      = 'ORDER';
    case ALGORITHM                  = 'ALGORITHM';
    case LOCK                       = 'LOCK';
    case ENABLE                     = 'ENABLE';
    case DISABLE                    = 'DISABLE';
    case FORCE                      = 'FORCE';
    case IGNORE                     = 'IGNORE';
    case VALIDATION                 = 'VALIDATION';
    case UPGRADE                    = 'UPGRADE';
    case REBUILD                    = 'REBUILD';
    case REORGANIZE                 = 'REORGANIZE';
    case ANALYZE                    = 'ANALYZE';
    case CHECK                      = 'CHECK';
    case OPTIMIZE                   = 'OPTIMIZE';
    case REPAIR                     = 'REPAIR';
    case TRUNCATE                   = 'TRUNCATE';
    case IMPORT                     = 'IMPORT';
    case DISCARD                    = 'DISCARD';
    case EXCHANGE                   = 'EXCHANGE';
}
