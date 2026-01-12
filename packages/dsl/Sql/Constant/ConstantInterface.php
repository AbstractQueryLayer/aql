<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

interface ConstantInterface extends \IfCastle\AQL\Dsl\Node\NodeInterface
{
    public function getConstantType(): string;

    public function getConstantValue(): mixed;

    public function isValueList(): bool;

    /**
     * Returns TRUE if constant value is variable (dynamically defined in the code)
     * From the point of view of SQL, the value is a constant, but from the point of view of business logic, it is a variable.
     * This flag affects the understanding of the meaning of the Query.
     */
    public function isVariable(): bool;

    public function asVariable(): static;

    public function asPlaceholder(?string $placeholder = null): static;

    public function getPlaceholder(): string|null;
}
