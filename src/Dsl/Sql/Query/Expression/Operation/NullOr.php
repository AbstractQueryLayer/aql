<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

class NullOr extends LROperation
{
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '(' . $this->childNodes[self::LEFT]->getAql($forResolved) . ' IS NULL OR ' . parent::getAql($forResolved) . ')';
    }
}
