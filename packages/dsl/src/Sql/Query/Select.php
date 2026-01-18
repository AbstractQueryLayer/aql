<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Tuple\Tuple;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\Exceptions\RequiredValueEmpty;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class Select extends QueryAbstract implements SelectInterface
{
    /**
     * @throws RequiredValueEmpty
     * @throws NodeException
     */
    public static function entity(string $entity): static
    {
        return new static($entity);
    }

    /**
     * Alias for entity.
     *
     *
     * @throws NodeException
     * @throws RequiredValueEmpty
     */
    public static function from(string $entity): static
    {
        return new static($entity);
    }

    public static function single(string $entity): static
    {
        return new static($entity, null, null, new Limit(1));
    }

    protected string $queryAction   = self::ACTION_SELECT;

    protected ?UnionEnum $unionType = null;

    protected ?string $unionOption  = null;

    /**
     * @throws RequiredValueEmpty
     * @throws NodeException
     */
    public function __construct(JoinInterface|string                 $from,
        TupleInterface|array|null            $columns   = null,
        Where|ConditionsInterface|array|null $where     = null,
        ?LimitInterface                       $limit     = null)
    {
        parent::__construct();

        if (\is_string($from)) {
            $from                   = new From(new Subject(\ucfirst($from)));
        }

        $this->setFrom($from);

        if (\is_array($columns)) {
            $columns                = new Tuple(...$columns)->markAsDefaultColumns();
        }

        if ($columns === null) {
            $columns                = new Tuple()->markAsDefaultColumns();
        }

        if ($columns instanceof TupleInterface === false) {
            throw new NodeException($this, 'Columns should be instance of Columns');
        }

        $this->setTuple($columns);

        if ($where !== null) {
            $this->setWhere($where);
        }

        if ($limit !== null) {
            $this->setLimit($limit);
        }
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $childNodes                 = $this->childNodes;
        $union                      = $this->getUnion();

        unset($childNodes[self::NODE_UNION]);

        $results                    = $this->nodesToAql($childNodes, ' ', $forResolved);

        if ($results === '') {
            return '';
        }

        $query                      = \strtoupper($this->queryAction) . ' ' . $results;

        if ($union->isEmpty()) {

            if ($this->getParentUnion()?->needParenthesis()) {
                $query              = "(\n" . $query . "\n)";
            }

            if ($this->unionType !== null) {
                $option             = $this->unionOption !== null ? ' ' . $this->unionOption . ' ' : '';
                $query              = "\n " . $this->unionType->value . $option . "\n" . $query . "\n";
            }

            return $query;
        }

        $parentUnion                = $this->getParentUnion();

        if ($union->needParenthesis() || $parentUnion?->needParenthesis()) {
            $query                  = "(\n" . $query . "\n)";
        }

        $query                      = $query . "\n" . $union->getAql($forResolved);

        if ($this->unionType !== null) {
            $option                 = $this->unionOption !== null ? ' ' . $this->unionOption . ' ' : '';
            $query                  = "\n " . $this->unionType->value . $option . "\n" . "\n(\n" . $query . "\n)";
        }

        return $query;
    }

    #[\Override]
    protected function generateResultForChildNodes(): array
    {
        $results                    = [];

        foreach ($this->childNodes as $childNode) {

            if ($childNode instanceof NodeInterface && $childNode instanceof UnionInterface === false) {
                $result             = $childNode->getResult();

                if (!empty($result)) {
                    $results[]      = $result;
                }
            }
        }

        return $results;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $results                    = $this->generateResultForChildNodes();

        if ($results === []) {
            return '';
        }

        $query                      = \strtoupper($this->queryAction) . ' ' . \implode(' ', $results);

        $union                      = $this->getUnion();

        if ($union->isEmpty()) {

            if ($this->getParentUnion()?->needParenthesis()) {
                $query              = "(\n" . $query . "\n)";
            }

            if ($this->unionType !== null) {
                $option             = $this->unionOption !== null ? ' ' . $this->unionOption . ' ' : '';
                $query              = "\n " . $this->unionType->value . $option . "\n" . $query . "\n";
            }

            return $query;
        }

        $parentUnion                = $this->getParentUnion();

        if ($union->needParenthesis() || $parentUnion?->needParenthesis()) {
            $query                  = "(\n" . $query . "\n)";
        }

        $query                      = $query . "\n" . $union->getResult();

        if ($this->unionType !== null) {
            $option                 = $this->unionOption !== null ? ' ' . $this->unionOption . ' ' : '';
            $query                  = "\n " . $this->unionType->value . $option . "\n" . "\n(\n" . $query . "\n)";
        }

        return $query;
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        $query                      = new self($array[self::MAIN_ENTITY] ?? null);
        $query->childNodes          = ArrayTyped::unserializeList($array[self::CHILD_NODES] ?? []);
        return $query;
    }

    #[\Override]
    public function column(string|NodeInterface ...$columns): static
    {
        $tuple                      = $this->getTuple();

        foreach ($columns as $column) {
            if (\is_string($column)) {
                $column             = new TupleColumn(new Column($column));
            }

            $tuple->addTupleColumn($column);
        }

        return $this;
    }

    #[\Override]
    public function getUnionType(): ?UnionEnum
    {
        return $this->unionType;
    }

    #[\Override]
    public function getUnionOption(): ?string
    {
        return $this->unionOption;
    }

    #[\Override]
    public function getUnion(): UnionInterface
    {
        return $this->childNodes[self::NODE_UNION];
    }

    #[\Override]
    public function asUnion(UnionEnum $union, ?string $option = null): static
    {
        $this->unionType            = $union;
        $this->unionOption          = $option;

        return $this;
    }

    #[\Override]
    public function join(string $entity): static
    {
        $this->getFrom()?->addJoin(
            new Join('', new Subject($entity)),
        );

        return $this;
    }

    #[\Override]
    public function fromSelect(SubqueryInterface $select): static
    {
        return $this->setFromSelect($select);
    }

    #[\Override]
    public function isSelect(): bool
    {
        return true;
    }

    #[\Override]
    protected function initChildNodes(): void
    {
        parent::initChildNodes();

        $this->childNodes[self::NODE_WHERE]     = new Where()->setParentNode($this);
        $this->childNodes[self::NODE_GROUP_BY]  = new GroupBy()->setParentNode($this);
        $this->childNodes[self::NODE_ORDER_BY]  = new OrderBy()->setParentNode($this);
        $this->childNodes[self::NODE_HAVING]    = new Conditions()->setParentNode($this);
        $this->childNodes[self::NODE_LIMIT]     = new Limit()->setParentNode($this);
        $this->childNodes[self::NODE_UNION]     = new Union()->setParentNode($this);
    }

    protected function getParentUnion(): UnionInterface|null
    {
        $parent                     = $this->getParentNode();

        if (false === $parent instanceof NodeList) {
            return null;
        }

        $parent                     = $parent->getParentNode();

        if ($parent instanceof UnionInterface) {
            return $parent;
        }

        return null;
    }
}
