<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

interface SubqueryInterface extends QueryInterface
{
    /**
     * Limits the result of a sub-query to only one record.
     *
     * @return $this
     */
    public function returnOnlyOne(): static;

    public function shouldReturnOnlyOne(): bool;

    public function isFromSelect(): bool;

    public function asFromSelect(): static;

    /**
     * Deep search for derived entity over subqueries.
     */
    public function searchDerivedEntity(): string;

    public function getCteAlias(): string;

    public function setCteAlias(string $alias): static;
}
