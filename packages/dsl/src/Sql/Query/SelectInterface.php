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
     * Whether the query returns each distinct row once: `SELECT DISTINCT`.
     */
    public function isDistinct(): bool;

    /**
     * Makes the query return each distinct row once, `SELECT DISTINCT ...`; false returns every row it reads.
     * In a UNION it applies to this member only.
     */
    public function setDistinct(bool $distinct = true): static;

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
     * What the locking read does with rows another transaction has locked; null: it waits for them.
     */
    public function getLockWait(): ?LockWait;

    /**
     * Sets `SKIP LOCKED` or `NOWAIT` after the locking-read clause; null makes the read wait. Setting the lock mode
     * to null clears it too.
     *
     * @throws \LogicException when $wait is not null and the query is not a locking read
     */
    public function setLockWait(?LockWait $wait): static;

    /**
     * Makes the query a locking read with an exclusive lock: `SELECT ... FOR UPDATE`.
     * The lock lasts until the transaction the query runs in ends. The executors refuse a locking read outside a
     * transaction, anywhere but the top-level SELECT, and for a query that needs secondary queries.
     */
    public function forUpdate(): static;

    /**
     * Makes the query a locking read with a shared lock: `SELECT ... LOCK IN SHARE MODE`.
     */
    public function lockInShareMode(): static;

    /**
     * The SQL text with a shared lock written as `FOR SHARE`, the form PostgreSQL knows instead of
     * `LOCK IN SHARE MODE`. Any other query gives the same text as getResultAsString().
     * The query itself does not change: its AQL and its ordinary result keep the `LOCK IN SHARE MODE` form.
     */
    public function getResultWithForShare(): string;

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
