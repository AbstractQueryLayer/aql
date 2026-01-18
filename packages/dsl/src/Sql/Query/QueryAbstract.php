<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Node\NodeTransformerIterator;
use IfCastle\AQL\Dsl\Node\RecursiveIteratorByNodeIterator;
use IfCastle\AQL\Dsl\QueryOption;
use IfCastle\AQL\Dsl\QueryOptions;
use IfCastle\AQL\Dsl\QueryOptionsInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\DuplicateKeyInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitWithParameters;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderItemInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Using;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\DesignPatterns\ScopeControl\ScopeMutableTrait;
use IfCastle\DI\DisposableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

abstract class QueryAbstract extends NodeAbstract implements QueryInterface
{
    use ScopeMutableTrait;

    protected string $queryAction   = '';

    protected ?string $mainEntityName = null;

    /**
     * Query storage.
     */
    protected ?string $storage      = null;

    protected object|null $queryContext = null;

    protected array|null $parameters    = null;

    public function __construct()
    {
        parent::__construct();
        $this->initChildNodes();
    }

    #[\Override]
    public function __clone(): void
    {
        parent::__clone();

        $this->queryContext         = null;
        $this->parameters           = null;
    }

    #[\Override]
    public function dispose(): void
    {
        $queryContext               = $this->queryContext;
        $this->queryContext         = null;

        $exception                  = null;

        try {
            if ($queryContext instanceof DisposableInterface) {
                $queryContext->dispose();
            }
        } catch (\Throwable $exception) {
        }

        parent::dispose();

        if ($exception !== null) {
            throw $exception;
        }
    }

    #[\Override]
    public function setNodeContext(object $context): void
    {
        if ($this->getParentNode() !== null) {
            parent::setNodeContext($context);
            return;
        }

        /**
         * Explanation:
         * The Query object is an external object that interacts with the user's code.
         * Objects such as the queryContext and the queryExecutor
         * are bound to the query object for the duration of its lifecycle.
         *
         * Therefore, it makes sense that the query OWNS the context
         * and all other objects related to the query execution.
         *
         */
        $this->queryContext         = $context;
        parent::setNodeContext($context);
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        $query                      = new static();
        $query->queryAction         = $array[self::ACTION] ?? '';
        $query->mainEntityName      = $array[self::MAIN_ENTITY] ?? null;
        $query->childNodes          = ArrayTyped::unserializeList($array[self::CHILD_NODES] ?? [], $validator);

        return $query;
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::ACTION            => $this->queryAction,
            self::MAIN_ENTITY       => $this->mainEntityName,
            self::CHILD_NODES       => ArrayTyped::serializeList($validator, ...$this->childNodes),
        ];
    }

    #[\Override]
    public function getQueryAction(): string
    {
        return $this->queryAction;
    }

    #[\Override]
    public function getQueryStorage(): ?string
    {
        return $this->storage;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setQueryStorage(?string $storage): static
    {
        $this->storage              = $storage;
        return $this;
    }

    #[\Override]
    public function isSelect(): bool
    {
        return false;
    }

    #[\Override]
    public function isInsert(): bool
    {
        return false;
    }

    #[\Override]
    public function isCopy(): bool
    {
        return false;
    }

    #[\Override]
    public function isUpdate(): bool
    {
        return false;
    }

    #[\Override]
    public function isDelete(): bool
    {
        return false;
    }

    #[\Override]
    public function isInsertUpdate(): bool
    {
        return false;
    }

    #[\Override]
    public function isResolvedAsSelect(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsSelect();
        }

        return $this->isSelect();
    }

    #[\Override]
    public function isResolvedAsInsert(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsInsert();
        }

        return $this->isInsert();
    }

    #[\Override]
    public function isResolvedAsClone(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsClone();
        }

        return $this->isCopy();
    }

    #[\Override]
    public function isResolvedAsUpdate(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsUpdate();
        }

        return $this->isUpdate();
    }

    #[\Override]
    public function isResolvedAsDelete(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsDelete();
        }

        return $this->isDelete();
    }

    #[\Override]
    public function isResolvedAsInsertUpdate(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsInsertUpdate();
        }

        return $this->isInsertUpdate();
    }

    #[\Override]
    public function isResolvedAsModifying(): bool
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->isResolvedAsModifying();
        }

        return $this->isModifying();
    }

    #[\Override]
    public function isModifying(): bool
    {
        return false;
    }

    #[\Override]
    public function getResolvedAction(): string
    {
        if ($this->substitution instanceof BasicQueryInterface) {
            return $this->substitution->getResolvedAction();
        }

        return $this->queryAction;
    }

    #[\Override]
    public function getMainEntityName(): string
    {
        if ($this->mainEntityName !== null) {
            return $this->mainEntityName;
        }

        $from                       = $this->getFrom();

        if ($from !== null) {
            return $from->getSubject()->getSubjectName();
        }

        return '';
    }

    #[\Override]
    public function setMainEntityName(string $entityName): static
    {
        $this->mainEntityName       = $entityName;
        return $this;
    }

    #[\Override]
    public function getQueryOptions(): QueryOptionsInterface
    {
        return $this->childNodes[self::NODE_OPTIONS];
    }

    /**
     * @throws TransformationException
     */
    #[\Override]
    public function addChildrenToBasicNode(string $nodeType, NodeInterface ...$nodes): void
    {
        if (false === \array_key_exists($nodeType, $this->childNodes)) {
            throw new TransformationException([
                'template'          => 'Unknown basic node type {nodeType} for adding child nodes',
                'nodeType'          => $nodeType,
            ]);
        }

        $basicNode                  = $this->childNodes[$nodeType];

        if (false === $basicNode instanceof ChildNodeMutableInterface) {
            throw new TransformationException([
                'template'          => 'Basic node {nodeType} is not mutable for adding child nodes',
                'nodeType'          => $nodeType,
            ]);
        }

        $basicNode->addChildNode(...$nodes);
    }

    #[\Override]
    public function isOption(string $name): bool
    {
        return $this->getQueryOptions()->isOption($name);
    }

    #[\Override]
    public function withPreparing(): static
    {
        $this->getQueryOptions()
             ->addOption(new QueryOption(self::PREPARING, true, true));
        return $this;
    }

    #[\Override]
    public function getTuple(): ?TupleInterface
    {
        return $this->childNodes[self::NODE_TUPLE] ?? null;
    }

    #[\Override]
    public function getAssigmentList(): ?AssignmentListInterface
    {
        return $this->childNodes[self::NODE_ASSIGMENT_LIST] ?? null;
    }

    #[\Override]
    public function getValueList(): ?ValueListInterface
    {
        return $this->childNodes[self::NODE_VALUE_LIST] ?? null;
    }

    #[\Override]
    public function findAssigmentValues(string|ColumnInterface ...$columns): array
    {
        if ($this->getAssigmentList()->isListNotEmpty()) {
            return $this->getAssigmentList()->findRightNodes(...$columns);
        }

        if ($this->getValueList()->isListNotEmpty()) {
            return $this->getValueList()->findValues(...$columns);
        }

        return [];
    }

    #[\Override]
    public function getFromSelect(): ?SubqueryInterface
    {
        return $this->childNodes[self::NODE_FROM_SELECT] ?? null;
    }

    #[\Override]
    public function getFrom(): ?JoinInterface
    {
        return $this->childNodes[self::NODE_FROM] ?? null;
    }

    #[\Override]
    public function getUsing(): ?Using
    {
        return $this->childNodes[self::NODE_USING] ?? null;
    }

    #[\Override]
    public function getWhere(): ?ConditionsInterface
    {
        return $this->childNodes[self::NODE_WHERE] ?? null;
    }

    #[\Override]
    public function where(string|NodeInterface $left, bool|int|string|NodeInterface|null $right): static
    {
        $this->getWhere()?->equal($left, $right);
        return $this;
    }

    #[\Override]
    public function wherePrimary(bool|int|string|NodeInterface $right): static
    {
        $this->getWhere()?->primaryKey($right);
        return $this;
    }

    #[\Override]
    public function getOrderBy(): ?OrderByInterface
    {
        return $this->childNodes[self::NODE_ORDER_BY] ?? null;
    }

    #[\Override]
    public function orderByAsc(NodeInterface|string $node): static
    {
        if ($node instanceof OrderItemInterface) {
            $this->getOrderBy()?->addOrderItem($node);
        } elseif (\is_string($node)) {
            $this->getOrderBy()?->addAsc(new Column($node));
        } else {
            $this->getOrderBy()?->addAsc($node);
        }

        return $this;
    }

    #[\Override]
    public function orderByDesc(NodeInterface|string $node): static
    {
        if ($node instanceof OrderItemInterface) {
            $this->getOrderBy()?->addOrderItem($node);
        } elseif (\is_string($node)) {
            $this->getOrderBy()?->addDesc(new Column($node));
        } else {
            $this->getOrderBy()?->addDesc($node);
        }

        return $this;
    }

    #[\Override]
    public function getGroupBy(): ?GroupByInterface
    {
        return $this->childNodes[self::NODE_GROUP_BY] ?? null;
    }

    public function groupBy(NodeInterface|string ...$nodes): static
    {
        $groupBy                    = $this->getGroupBy();

        if ($groupBy === null) {
            return $this;
        }

        foreach ($nodes as $node) {
            if (\is_string($node)) {
                $node               = new Column($node);
            }

            $groupBy->addGroupBy($node);
        }

        return $this;
    }

    #[\Override]
    public function onDuplicateKey(): ?DuplicateKeyInterface
    {
        return $this->childNodes[self::NODE_DUPLICATE_KEY] ?? null;
    }

    #[\Override]
    public function getHaving(): ?ConditionsInterface
    {
        return $this->childNodes[self::NODE_HAVING] ?? null;
    }

    #[\Override]
    public function having(NodeInterface ...$conditions): static
    {
        $this->getHaving()?->apply($conditions);

        return $this;
    }

    #[\Override]
    public function getLimit(): ?LimitInterface
    {
        return $this->childNodes[self::NODE_LIMIT] ?? null;
    }

    #[\Override]
    public function limit(int $limit, int $offset = 0): static
    {
        $this->setLimit(new Limit($limit, $offset));

        return $this;
    }

    #[\Override]
    public function limitWith(ConstantInterface $limit, ConstantInterface $offset = new Variable(0)): static
    {
        $this->setLimit(new LimitWithParameters($limit, $offset));

        return $this;
    }

    #[\Override]
    public function setQueryOptions(QueryOptionsInterface $queryOptions): static
    {
        $this->childNodes[self::NODE_OPTIONS] = $queryOptions->setParentNode($this);
        return $this;
    }

    #[\Override]
    public function setTuple(TupleInterface $tuple): static
    {
        $this->childNodes[self::NODE_TUPLE] = $tuple->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setAssigmentList(AssignmentListInterface $assignmentList): static
    {
        $this->childNodes[self::NODE_ASSIGMENT_LIST] = $assignmentList->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setValueList(ValueListInterface $valueList): static
    {
        $this->childNodes[self::NODE_VALUE_LIST] = $valueList->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setFromSelect(SubqueryInterface $subquery): static
    {
        $this->childNodes[self::NODE_FROM_SELECT] = $subquery->setParentNode($this)->asFromSelect();

        return $this;
    }

    #[\Override]
    public function setFrom(JoinInterface $join): static
    {
        $this->childNodes[self::NODE_FROM] = $join->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setUsing(Using $using): static
    {
        $this->childNodes[self::NODE_USING] = $using->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setWhere(ConditionsInterface|array $conditions): static
    {
        if (\is_array($conditions)) {
            $conditions             = new Where()->apply($conditions);
        }

        $this->childNodes[self::NODE_WHERE] = $conditions->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setOrderBy(OrderByInterface $orderBy): static
    {
        $this->childNodes[self::NODE_ORDER_BY] = $orderBy->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setGroupBy(GroupByInterface $groupBy): static
    {
        $this->childNodes[self::NODE_GROUP_BY] = $groupBy->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setDuplicateKey(DuplicateKeyInterface $duplicateKey): static
    {
        $this->childNodes[self::NODE_DUPLICATE_KEY] = $duplicateKey->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setHaving(ConditionsInterface $conditions): static
    {
        $this->childNodes[self::NODE_HAVING] = $conditions->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function setLimit(LimitInterface $limit): static
    {
        $this->childNodes[self::NODE_LIMIT] = $limit->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function findMainSubject(): ?SubjectInterface
    {
        if ($this->getFromSelect() !== null) {
            return $this->getFromSelect()->findMainSubject();
        }

        if ($this->getFrom() !== null) {
            return $this->getFrom()->getSubject();
        }

        return null;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $results                    = $this->childNodesToAql(' ', $forResolved);

        if ($results === '') {
            return '';
        }

        return \strtoupper($this->queryAction) . ' ' . $results;
    }

    #[\Override]
    public function getQueryParameters(): array
    {
        if ($this->parameters === null) {
            $this->resolveParameters();
        }

        if ($this->parameters === []) {
            return [];
        }

        $parameters                 = [];

        foreach ($this->parameters as $key => $parameter) {
            $parameter = $parameter->get();

            if ($parameter !== null) {
                $parameters[$key]       = $parameter;
            }
        }

        return $parameters;
    }

    #[\Override]
    public function addQueryParameter(ParameterInterface ...$parameters): static
    {
        if ($this->parameters === null) {
            $this->parameters       = [];
        }

        foreach ($parameters as $parameter) {
            if (\array_key_exists($parameter->getParameterName(), $this->parameters)
                && $this->parameters[$parameter->getParameterName()]->get() !== null) {
                $parameter->referenceTo($this->parameters[$parameter->getParameterName()]->get());
            } else {
                $this->parameters[$parameter->getParameterName()] = \WeakReference::create($parameter);
            }
        }

        return $this;
    }

    #[\Override]
    public function applyParameters(array $parameters): static
    {
        if ($this->parameters === null) {
            $this->resolveParameters();
        }

        foreach ($parameters as $key => $value) {
            if (\array_key_exists($key, $this->parameters)) {
                $this->parameters[$key]->get()?->setParameterValue($value);
            }
        }

        return $this;
    }

    #[\Override]
    public function applyParameter(string $name, mixed $value): static
    {
        if ($this->parameters === null) {
            $this->resolveParameters();
        }

        if (\array_key_exists($name, $this->parameters)) {
            $this->parameters[$name]->get()?->setParameterValue($value);
        }

        return $this;
    }

    protected function resolveParameters(): void
    {
        $this->parameters           = [];

        foreach (new RecursiveIteratorByNodeIterator(new NodeTransformerIterator($this)) as $node) {
            if ($node instanceof ParameterInterface) {
                $this->addQueryParameter($node);
            }
        }
    }

    protected function initChildNodes(): void
    {
        $this->childNodes           = [
            self::NODE_OPTIONS      => new QueryOptions()->setParentNode($this),
            self::NODE_TUPLE        => null,
            self::NODE_USING        => null,
            self::NODE_FROM         => null,
            self::NODE_ASSIGMENT_LIST => null,
            self::NODE_VALUE_LIST   => null,
            self::NODE_FROM_SELECT  => null,
            self::NODE_WHERE        => null,
            self::NODE_GROUP_BY     => null,
            self::NODE_ORDER_BY     => null,
            self::NODE_DUPLICATE_KEY => null,
            self::NODE_HAVING       => null,
            self::NODE_LIMIT        => null,
        ];
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $results                    = $this->generateResultForChildNodes();

        if ($results === []) {
            return '';
        }

        return $this->queryAction . ' ' . \implode(' ', $results);
    }
}
