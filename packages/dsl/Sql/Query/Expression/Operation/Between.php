<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression\Operation;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

/**
 * Class Between
 * expr BETWEEN min AND max
 * see: https://dev.mysql.com/doc/refman/8.0/en/comparison-operators.html#operator_between.
 */
class Between extends NodeAbstract implements OperationInterface
{
    public const string BETWEEN     = 'BETWEEN';

    public const string MIN         = 'MIN';

    public const string MAX         = 'MAX';

    public function __construct(NodeInterface $min, NodeInterface $max)
    {
        parent::__construct();
        $this->childNodes[self::MIN] = $min;
        $this->childNodes[self::MAX] = $max;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return self::BETWEEN . ' ' . $this->childNodes[self::MIN]->getAql($forResolved) . ' AND ' . $this->childNodes[self::MAX]->getAql(
            $forResolved
        );
    }

    public function getMin(): NodeInterface
    {
        return $this->childNodes[self::MIN];
    }

    public function getMax(): NodeInterface
    {
        return $this->childNodes[self::MAX];
    }

    #[\Override]
    public function getOperation(): string
    {
        return self::BETWEEN;
    }

    #[\Override]
    protected function generateResult(): string
    {
        return self::BETWEEN . ' '
                . $this->childNodes[self::MIN]->generateResult() . ' AND '
                . $this->childNodes[self::MIN]->generateResult();
    }
}
