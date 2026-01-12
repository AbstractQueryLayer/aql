<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

interface SubqueryAggregatorInterface
{
    public function getSubquery(): SubqueryInterface;
}
