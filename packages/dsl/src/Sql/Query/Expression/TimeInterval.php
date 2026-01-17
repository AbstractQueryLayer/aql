<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;

/**
 * Time interval expression
 * like: INTERVAL 1 DAY or INTERVAL 1 YEAR.
 */
class TimeInterval extends NodeAbstract
{
    protected string $nodeName       = 'TIME_INTERVAL';

    public function __construct(protected TimeIntervalEnum $interval, protected int $value)
    {
        parent::__construct();
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return 'INTERVAL ' . $this->value . ' ' . $this->interval->value;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return 'INTERVAL ' . $this->value . ' ' . $this->interval->value;
    }
}
