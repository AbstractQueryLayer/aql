<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\Exceptions\LogicalException;

/**
 * # Nested tuple.
 *
 * ## Example: get users and groups
 *
 * AQL: SELECT name, [id, name FROM Groups WHERE is_active] as groups FROM Users
 * Result:
 * [
 *  {"name1", [{"id": "123", "name": "Group1"}, {"id": "124", "state": "Group2"}]},
 *  {"name2", [{"id": "223", "name": "Group3"}]}
 * ]
 *
 */
class NestedTuple extends Select implements SubqueryInterface
{
    protected string $cteAlias      = '';

    #[\Override]
    public function returnOnlyOne(): static
    {
        return $this;
    }

    #[\Override]
    public function shouldReturnOnlyOne(): bool
    {
        return false;
    }

    #[\Override]
    public function searchDerivedEntity(): string
    {
        return '';
    }

    #[\Override]
    public function getCteAlias(): string
    {
        return $this->cteAlias;
    }

    #[\Override]
    public function setCteAlias(string $alias): static
    {
        $this->cteAlias             = $alias;
        return $this;
    }

    #[\Override]
    public function isFromSelect(): bool
    {
        return false;
    }

    /**
     * @throws LogicalException
     */
    #[\Override]
    public function asFromSelect(): static
    {
        throw new LogicalException('NestedTuple cannot be used as a FROM SELECT subquery');
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $results                    = $this->childNodesToAql(' ', $forResolved);

        if ($results === '') {
            return '';
        }

        return '[' . $results . ']';
    }
}
