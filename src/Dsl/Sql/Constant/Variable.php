<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

/**
 * Create SQL constant expression as Variable.
 */
class Variable extends Constant
{
    protected bool $isVariable      = true;
}
