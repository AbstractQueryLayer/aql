<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface OrderItemInterface extends NodeInterface
{
    /**
     * @var string
     */
    public const string DESC        = 'DESC';

    /**
     * @var string
     */
    public const string ASC         = 'ASC';

    public function getDirection(): string;

    public function setDirection(string $direction): static;
}
