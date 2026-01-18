<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Conditions;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\OperationInterface;

interface ConditionsInterface extends NodeInterface, ChildNodeMutableInterface
{
    public const string TYPE_AND    = 'AND';

    public const string TYPE_OR     = 'OR';

    public function isConditionsExist(): bool;

    public function isConditionsEmpty(): bool;

    public function add(OperationInterface $condition): static;

    public function apply(array $conditions): static;

    public function primaryKey(NodeInterface|string|int|float $right): static;

    public function equal(NodeInterface|string $left, NodeInterface|string|int|float|bool $right): static;

    public function notEqual(NodeInterface|string $left, NodeInterface|string|int|float|bool $right): static;

    public function like(NodeInterface|string $left, NodeInterface|string $right): static;

    public function notLike(NodeInterface|string $left, NodeInterface|string $right): static;

    public function greater(NodeInterface|string $left, NodeInterface|string|int|float $right): static;

    public function less(NodeInterface|string $left, NodeInterface|string|int|float $right): static;

    public function greaterOrEqual(NodeInterface|string $left, NodeInterface|string|int|float $right): static;

    public function lessOrEqual(NodeInterface|string $left, NodeInterface|string|int|float $right): static;

    public function isNotNull(NodeInterface|string $left): static;

    public function isNull(NodeInterface|string $left): static;

    public function nullOr(NodeInterface|string $left, NodeInterface|string|int|float $right): static;

    public function isTrue(NodeInterface|string $left): static;

    public function isFalse(NodeInterface|string $left): static;

    public function subAnd(): ConditionsInterface;

    public function subOr(): ConditionsInterface;

    public function findPropertyFilter(string $propertyName): ?LROperationInterface;

    public function getFirstOperation(): OperationInterface;

    public function countOfOperations(): int;

    /**
     * Returns TRUE if condition:
     * 1. Contains only one element
     * 2. The First element is LROperation
     * 3. left and right side is Field or Constant.
     * 4. LROperation is equal
     */
    public function isPure(): bool;

    public function reverseConditions(): static;
}
