<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

class OrderItem extends NodeAbstract implements OrderItemInterface
{
    public function __construct(NodeInterface $expression, protected string $direction = self::ASC)
    {
        parent::__construct();
        $this->childNodes[]         = $expression->setParentNode($this);
    }

    #[\Override]
    public function getDirection(): string
    {
        return $this->direction;
    }

    #[\Override]
    public function setDirection(string $direction): static
    {
        $this->direction            = $direction;

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->childNodes === []) {
            return '';
        }

        $aql                        = $this->childNodes[0]->getAql($forResolved);

        if ($aql === '') {
            return '';
        }

        if ($this->direction === self::ASC) {
            return $aql;
        }

        return $aql . ' ' . $this->direction;
    }
}
