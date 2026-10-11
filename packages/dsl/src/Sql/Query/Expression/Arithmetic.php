<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\Exceptions\UnexpectedValue;

/**
 * Additive expression `left + right` or `left - right`.
 *
 * Either side may be any value expression: a column, a constant, a function call, another Arithmetic or a
 * TimeInterval (`UTC_TIMESTAMP(6) + INTERVAL 30 SECOND`). A nested Arithmetic on the right side is parenthesized when
 * rendered, so a tree built in code keeps its meaning: `a - (b - c)`.
 */
class Arithmetic extends NodeAbstract
{
    public const string PLUS        = '+';

    public const string MINUS       = '-';

    public const int LEFT           = 0;

    public const int RIGHT          = 1;

    protected string $nodeName      = 'ARITHMETIC';

    /**
     * @param string $operator  PLUS or MINUS
     * @throws UnexpectedValue  when the operator is neither
     */
    public function __construct(NodeInterface $left, protected string $operator, NodeInterface $right)
    {
        if ($operator !== self::PLUS && $operator !== self::MINUS) {
            throw new UnexpectedValue('$operator', $operator, self::PLUS . ' or ' . self::MINUS);
        }

        parent::__construct($left, $right);
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getLeftNode(): NodeInterface
    {
        return $this->childNodes[self::LEFT];
    }

    public function getRightNode(): NodeInterface
    {
        return $this->childNodes[self::RIGHT];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->join(
            $this->childNodes[self::LEFT]->getAql($forResolved),
            $this->childNodes[self::RIGHT]->getAql($forResolved)
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

        return $this->join($left, $right);
    }

    private function join(string $left, string $right): string
    {
        if ($this->childNodes[self::RIGHT] instanceof self) {
            $right                  = '(' . $right . ')';
        }

        return $left . ' ' . $this->operator . ' ' . $right;
    }
}
