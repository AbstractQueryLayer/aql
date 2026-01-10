<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

/**
 * Expression:
 * WHERE fieldRef
 * alias for
 * WHERE fieldRef = 1
 */
class SingleOperation extends NodeAbstract implements OperationInterface
{
    public const string EXPRESSION = 'expression';

    public function __construct(NodeInterface $expression)
    {
        parent::__construct();
        $this->childNodes[self::EXPRESSION] = $expression->setParentNode($this);
    }

    public function getExpression(): NodeInterface
    {
        return $this->childNodes[self::EXPRESSION];
    }

    #[\Override]
    public function getOperation(): string
    {
        return '';
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->getExpression()->getAql($forResolved);
    }
}
