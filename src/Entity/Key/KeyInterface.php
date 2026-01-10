<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Key;

interface KeyInterface
{
    public function getKeyName(): string;

    /**
     * Returns name of columns.
     *
     * @return string[]
     */
    public function getKeyColumns(): array;

    public function isPrimary(): bool;

    public function isUnique(): bool;

    public function isFulltext(): bool;

    public function isKeySimple(): bool;

    public function isKeyComplex(): bool;

    /**
     * Returns true if keys are equals.
     *
     *
     */
    public function isEquals(KeyInterface $key): bool;

    public function asPrimary(): static;

    public function asUnique(): static;

    public function asFulltext(): static;
}
