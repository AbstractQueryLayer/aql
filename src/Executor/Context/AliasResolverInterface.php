<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

use IfCastle\AQL\Entity\EntityInterface;

interface AliasResolverInterface
{
    /**
     * Find alias inside context including foreign aliases and parent aliases.
     *
     *
     */
    public function findAlias(string $subject, bool $isForeign = false): ?string;

    /**
     * Returns the alias of the entity if alias exists in the current context.
     *
     *
     */
    public function isAliasExists(string $subject): bool;

    /**
     * Returns or defines an alias for the entity.
     *
     *
     */
    public function defineAlias(string|EntityInterface $entity, bool $isForeign = false): string;

    public function generateAlias($prefix = 't'): string;

    /**
     * Defines an alias namespace.
     *
     *
     * @return $this
     */
    public function setAliasesNamespace(string $aliasesNamespace): static;

    /**
     * Specifies to create new aliases for entities, rather than inherit them.
     * @return $this
     */
    public function notInheritAliases(): static;
}
