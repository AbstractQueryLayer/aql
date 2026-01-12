<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

interface WithInterface extends QueryInterface
{
    final public const string NODE_SUBQUERIES = 'subqueries';

    final public const string NODE_QUERY      = 'query';

    /**
     * @return SubqueryInterface[]
     */
    public function getSubqueries(): array;

    /**
     * @param SubqueryInterface[] $subqueries
     * @return $this
     */
    public function defineSubqueries(array $subqueries): static;

    public function getDefaultCteName(): string|null;

    public function findSubqueryByName(string $cteName): SubqueryInterface|null;

    public function findCteNameByEntityName(string $entityName): string|null;

    public function isRecursive(): bool;

    public function asRecursive(): static;

    public function getQuery(): QueryInterface|null;

    public function defineQuery(QueryInterface $query): static;
}
