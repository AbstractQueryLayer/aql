<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

class From extends Join
{
    #[\Override]
    public static function newFromSubquery(SubqueryInterface $subquery, string $joinType, string $alias): static
    {
        $join                       = new self(new Subject('', '', $alias));
        $join->childNodes[self::NODE_SUBQUERY] = $subquery->setParentNode($join);
        return $join;
    }

    public function __construct(Subject $subject, JoinInterface ...$joins)
    {
        parent::__construct('FROM', $subject);
        $this->childNodes[self::NODE_JOINS] = (new NodeList(...$joins))->defineDelimiter("\n")->setParentNode($this);
    }
}
