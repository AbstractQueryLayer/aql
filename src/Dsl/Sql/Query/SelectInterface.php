<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface SelectInterface extends QueryInterface
{
    public function column(string|NodeInterface ...$columns): static;

    public function join(string $entity): static;

    public function fromSelect(SubqueryInterface $select): static;

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
