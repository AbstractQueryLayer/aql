<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

/**
 * [NOT] EXISTS (subquery): true when the subquery returns a row. Unlike NOT IN, NOT EXISTS stays true when a
 * compared column is NULL, so an anti-join over a nullable key keeps the rows that have no match.
 */
class Exists extends NodeAbstract implements OperationInterface
{
    public const string SUBQUERY    = 'subquery';

    public function __construct(SubqueryInterface $subquery, protected bool $isNot = false)
    {
        parent::__construct();
        $this->childNodes[self::SUBQUERY] = $subquery->setParentNode($this);
    }

    #[\Override]
    public function getOperation(): string
    {
        return $this->isNot ? 'NOT EXISTS' : 'EXISTS';
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->getOperation() . ' ' . $this->childNodes[self::SUBQUERY]->getAql($forResolved);
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return $this->getOperation() . ' ' . $this->childNodes[self::SUBQUERY]->getResult();
    }
}
