<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\TimeIntervalEnum;

final class DateSub extends DateAdd
{
    public function __construct(\DateTimeInterface|string|NodeInterface $date, TimeIntervalEnum $interval, int $value)
    {
        parent::__construct($date, $interval, $value, false);
    }
}
