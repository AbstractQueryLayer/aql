<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface SelectInterface extends QueryInterface
{
    public function column(string|NodeInterface ...$columns): static;

    public function join(string $entity): static;

    public function fromSelect(SubqueryInterface $select): static;

    /**
     * The locking-read mode, or null for an ordinary read.
     */
    public function getLockMode(): ?LockMode;

    /**
     * Sets the locking-read mode; null makes the query an ordinary read.
     * The clause renders at the very end of the SELECT, after LIMIT.
     */
    public function setLockMode(?LockMode $mode): static;

    /**
     * Makes the query a locking read with an exclusive lock: `SELECT ... FOR UPDATE`.
     * The lock lasts until the transaction the query runs in ends; outside a transaction it is released at once.
     */
    public function forUpdate(): static;

    /**
     * Makes the query a locking read with a shared lock: `SELECT ... LOCK IN SHARE MODE`.
     */
    public function lockInShareMode(): static;

    /**
     * Renders a shared lock as `FOR SHARE`, the form PostgreSQL knows instead of `LOCK IN SHARE MODE`.
     * The AQL form of the query does not change.
     */
    public function renderShareAsForShare(): static;

    public function getUnionType(): ?UnionEnum;

    public function getUnionOption(): ?string;

    /**
     * Return the list of unions subqueries.
     *
     */
    public function getUnion(): UnionInterface;

    /**
     * Mark the query as a union-subquery.
     *
     */
    public function asUnion(UnionEnum $union, ?string $option = null): static;
}
