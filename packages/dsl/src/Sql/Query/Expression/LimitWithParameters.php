<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;

final class LimitWithParameters extends NodeAbstract implements LimitInterface
{
    public function __construct(ConstantInterface $limit, ConstantInterface $offset = new Variable(0))
    {
        parent::__construct($offset, $limit);
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return $this->getOffset() > 0;
    }

    #[\Override]
    public function getOffset(): int
    {
        $node                       = $this->childNodes[0];

        if ($node instanceof ParameterInterface && false === $node->isParameterResolved()) {
            return 0;
        }

        return $node->getConstantValue();
    }

    #[\Override]
    public function getLimit(): int
    {
        $node                       = $this->childNodes[1];

        if ($node instanceof ParameterInterface && false === $node->isParameterResolved()) {
            return 0;
        }

        return $node->getConstantValue();
    }

    #[\Override]
    public function setOffset(int $offset): static
    {
        $node                       = $this->childNodes[0];

        if ($node instanceof ParameterInterface) {
            $node->setParameterValue($offset);
        }

        return $this;
    }

    #[\Override]
    public function setLimit(int $limit): static
    {
        $node                       = $this->childNodes[1];

        if ($node instanceof ParameterInterface) {
            $node->setParameterValue($limit);
        }

        return $this;
    }


    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->getLimit() === 0) {
            return '';
        }

        return 'LIMIT ' . $this->childNodesToAql(',', $forResolved);
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->getLimit() === 0) {
            return '';
        }

        $offset                     = $this->childNodes[0]->getResult();
        $limit                      = $this->childNodes[1]->getResult();

        if ($offset === '') {
            return 'LIMIT ' . $limit;
        }

        return 'LIMIT ' . $this->getOffset() . ', ' . $this->getLimit();
    }
}
