<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Query\Expression\TimeInterval;
use IfCastle\AQL\Dsl\Sql\Query\Expression\TimeIntervalEnum;

class DateAdd extends FunctionReference
{
    public function __construct(NodeInterface|\DateTimeInterface|string $date, TimeIntervalEnum $interval, int $value, bool $isAdd = true)
    {
        if ($date instanceof NodeInterface === false) {

            if ($date instanceof \DateTimeInterface) {
                $date                   = $date->format('Y-m-d H:i:s');
            }

            $date                       = new Variable($date);
        }

        parent::__construct($isAdd ? 'DATE_ADD' : 'DATE_SUB', $date, new TimeInterval($interval, $value));
    }
}
