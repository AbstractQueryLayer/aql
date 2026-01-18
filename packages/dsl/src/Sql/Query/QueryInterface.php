<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\QueryOptionsInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\DuplicateKeyInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Using;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;

interface QueryInterface extends NodeInterface, BasicQueryInterface
{
    public const string NODE_OPTIONS    = 'OPTIONS';

    public const string NODE_TUPLE      = 'TUPLE';

    public const string NODE_ASSIGMENT_LIST = 'SET';

    public const string NODE_VALUE_LIST = 'VALUES';

    public const string NODE_FROM       = 'FROM';

    public const string NODE_FROM_SELECT = 'FROM_SELECT';

    public const string NODE_USING      = 'USING';

    public const string NODE_WHERE      = 'WHERE';

    public const string NODE_ORDER_BY   = 'ORDER BY';

    public const string NODE_GROUP_BY   = 'GROUP BY';

    public const string NODE_DUPLICATE_KEY = 'ON DUPLICATE KEY';

    public const string NODE_HAVING         = 'HAVING';

    public const string NODE_LIMIT          = 'LIMIT';

    public const string NODE_UNION          = 'UNION';

    public const string ACTION_SELECT       = 'SELECT';

    public const string ACTION_COUNT        = 'COUNT';

    public const string ACTION_INSERT       = 'INSERT';

    public const string ACTION_REPLACE      = 'REPLACE';

    public const string ACTION_COPY         = 'COPY';

    public const string ACTION_UPDATE       = 'UPDATE';

    public const string ACTION_DELETE       = 'DELETE';

    public const string ACTION_WITH         = 'WITH';

    public const string PREPARING           = 'preparing';

    /**
     * Current query is not stringable.
     */
    public const string NO_STRINGABLE       = 'no_stringable';

    /**
     * Indicates to use Preparing for queries if the database driver supports it.
     *
     * @return $this
     */
    public function withPreparing(): static;

    public function getTuple(): ?TupleInterface;

    public function getAssigmentList(): ?AssignmentListInterface;

    public function getValueList(): ?ValueListInterface;

    /**
     * Return array of a list of ConstantI where key is name of column.
     *
     *
     * @return  array<string, NodeInterface|NodeInterface[]>
     */
    public function findAssigmentValues(string|ColumnInterface ...$columns): array;

    public function getFromSelect(): ?SubqueryInterface;

    public function getFrom(): ?JoinInterface;

    public function getUsing(): ?Using;

    public function getWhere(): ?ConditionsInterface;

    public function where(NodeInterface|string $left, NodeInterface|string|int|bool|null $right): static;

    public function wherePrimary(NodeInterface|string|int|bool $right): static;

    public function getOrderBy(): ?OrderByInterface;

    public function getGroupBy(): ?GroupByInterface;

    public function groupBy(NodeInterface|string ...$nodes): static;

    public function onDuplicateKey(): ?DuplicateKeyInterface;

    public function getHaving(): ?ConditionsInterface;

    public function having(NodeInterface ...$conditions): static;

    public function getLimit(): ?LimitInterface;

    public function limit(int $limit, int $offset = 0): static;

    public function limitWith(ConstantInterface $limit, ConstantInterface $offset = new Variable(0)): static;

    public function setQueryOptions(QueryOptionsInterface $queryOptions): static;

    public function setTuple(TupleInterface $tuple): static;

    public function setAssigmentList(AssignmentListInterface $assignmentList): static;

    public function setValueList(ValueListInterface $valueList): static;

    public function setFromSelect(SubqueryInterface $subquery): static;

    public function setFrom(JoinInterface $join): static;

    public function setUsing(Using $using): static;

    public function setWhere(ConditionsInterface $conditions): static;

    public function setOrderBy(OrderByInterface $orderBy): static;

    public function orderByAsc(NodeInterface|string $node): static;

    public function orderByDesc(NodeInterface|string $node): static;

    public function setGroupBy(GroupByInterface $groupBy): static;

    public function setDuplicateKey(DuplicateKeyInterface $duplicateKey): static;

    public function setHaving(ConditionsInterface $conditions): static;

    public function setLimit(LimitInterface $limit): static;
}
