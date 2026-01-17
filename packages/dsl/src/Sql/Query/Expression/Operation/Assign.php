<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;

class Assign extends LROperation
{
    public function __construct(ColumnInterface $left, NodeInterface $right)
    {
        parent::__construct($left, self::ASSIGN, $right);
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->childNodes[self::LEFT]->getAql($forResolved) . ' = ' . $this->childNodes[self::RIGHT]->getAql(
            $forResolved
        );
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $left                       = $this->childNodes[self::LEFT]->getResult();
        $right                      = $this->childNodes[self::RIGHT]->getResult();

        if ($left === '' || $right === '') {
            return '';
        }

        return $left . ' = ' . $right;
    }
}
