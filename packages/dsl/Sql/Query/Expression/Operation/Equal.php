<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;

class Equal extends LROperation
{
    public static function primary(string|int|float|bool $right): static
    {
        return new static(new Column('@id'), new Constant($right));
    }

    public static function column(string $column, string|int|float|bool $right): static
    {
        return new static(new Column($column), new Constant($right));
    }

    public function __construct(NodeInterface $left, NodeInterface $right)
    {
        parent::__construct($left, self::EQU, $right);
    }
}
