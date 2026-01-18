<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Conditions;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\OperationInterface;
use IfCastle\DesignPatterns\Iterators\RecursiveIteratorByIterator;
use IfCastle\Exceptions\UnexpectedValueType;

class Conditions extends NodeAbstract implements ConditionsInterface
{
    public static function keyValueToExpressions(array $keyValueFilters): array
    {
        $result                    = [];

        foreach ($keyValueFilters as $column => $value) {
            if ($value instanceof NodeInterface) {
                $result[]           = $value;
            } else {
                $result[]           = new LROperation($column, LROperationInterface::EQU, Parameter::fromValue($value));
            }
        }

        return $result;
    }

    public function __construct(protected string $type = self::TYPE_AND, /**
     * The name of the entity that is the main one for these conditions.
     */
        protected ?string $ownerEntity = null)
    {
        parent::__construct();
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setType(string $type): static
    {
        $this->type                 = $type;
        return $this;
    }

    public function getOwnerEntity(): ?string
    {
        return $this->ownerEntity;
    }

    /**
     * @return $this
     */
    public function setOwnerEntity(string $ownerEntity): static
    {
        $this->ownerEntity          = $ownerEntity;
        return $this;
    }

    #[\Override]
    public function isConditionsExist(): bool
    {
        return $this->childNodes !== [];
    }

    #[\Override]
    public function isConditionsEmpty(): bool
    {
        return $this->childNodes === [];
    }

    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }

    #[\Override]
    public function add(NodeInterface $condition): static
    {
        $this->childNodes[]         = $condition->setParentNode($this);
        return $this;
    }

    #[\Override]
    public function apply(array $conditions): static
    {
        foreach ($conditions as $condition) {
            $this->add($condition);
        }

        return $this;
    }

    #[\Override]
    public function primaryKey(float|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation('@id', LROperationInterface::EQU, Parameter::fromValue($right)));
    }

    #[\Override]
    public function equal(NodeInterface|string $left, float|bool|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::EQU, Parameter::fromValue($right)));
    }

    #[\Override]
    public function notEqual(NodeInterface|string $left, float|bool|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::NOT_EQU, Parameter::fromValue($right)));
    }

    #[\Override]
    public function like(NodeInterface|string $left, string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::LIKE, Parameter::fromValue($right)));
    }

    #[\Override]
    public function notLike(NodeInterface|string $left, string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::NOT_LIKE, Parameter::fromValue($right)));
    }

    #[\Override]
    public function greater(NodeInterface|string $left, float|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::GREATER, Parameter::fromValue($right)));
    }

    #[\Override]
    public function less(NodeInterface|string $left, float|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::LESS, Parameter::fromValue($right)));
    }

    #[\Override]
    public function greaterOrEqual(NodeInterface|string $left, float|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::GREATER_EQU, Parameter::fromValue($right)));
    }

    #[\Override]
    public function lessOrEqual(NodeInterface|string $left, float|int|string|NodeInterface $right): static
    {
        return $this->add(new LROperation($left, LROperationInterface::LESS_EQU, Parameter::fromValue($right)));
    }

    #[\Override]
    public function isNotNull(NodeInterface|string $left): static
    {
        return $this->add(new LROperation($left, LROperationInterface::IS_NOT, new Constant()));
    }

    #[\Override]
    public function isNull(NodeInterface|string $left): static
    {
        return $this->add(new LROperation($left, LROperationInterface::IS, new Constant()));
    }

    #[\Override]
    public function nullOr(NodeInterface|string $left, float|int|string|NodeInterface $right): static
    {
        return $this->add(new Conditions(self::TYPE_OR, $this->ownerEntity)->isNull($left)->equal($left, $right));
    }

    #[\Override]
    public function isTrue(NodeInterface|string $left): static
    {
        return $this->add(new LROperation($left, LROperationInterface::IS, new Constant(true)));
    }

    #[\Override]
    public function isFalse(NodeInterface|string $left): static
    {
        return $this->add(new LROperation($left, LROperationInterface::IS, new Constant(false)));
    }

    #[\Override]
    public function subAnd(): ConditionsInterface
    {
        $conditions                 = new static();
        $this->childNodes[]         = $conditions;

        return $conditions;
    }

    #[\Override]
    public function subOr(): ConditionsInterface
    {
        $conditions                 = new static(self::TYPE_OR);
        $this->childNodes[]         = $conditions;

        return $conditions;
    }

    #[\Override]
    public function findPropertyFilter(string $propertyName): ?LROperationInterface
    {
        foreach ($this->childNodes as $expression) {

            $column                 = $expression instanceof LROperationInterface ? $expression->getLeftNode() : null;

            if ($column instanceof ColumnInterface && $column->getColumnName() === $propertyName) {
                return $expression;
            }
        }

        return null;
    }

    #[\Override]
    public function getFirstOperation(): OperationInterface
    {
        return $this->childNodes[0];
    }

    #[\Override]
    public function countOfOperations(): int
    {
        return \count($this->childNodes);
    }

    #[\Override]
    public function isPure(): bool
    {
        if ($this->childNodes === []) {
            return true;
        }

        if (\count($this->childNodes) > 1) {
            return false;
        }

        $expression                 = $this->childNodes[0];
        $expression                 = $expression->getSubstitution() ?? $expression;

        if ($expression instanceof ConditionsInterface) {
            return $expression->isPure();
        }

        if ($expression instanceof LROperationInterface === false) {
            return false;
        }

        /* @var $expression LROperationInterface */
        if ($expression->getOperation() !== LROperationInterface::EQU) {
            return false;
        }

        $left                       = $expression->getLeftNode();
        $right                      = $expression->getRightNode();

        $left                       = $left->getSubstitution() ?? $left;
        $right                      = $right->getSubstitution() ?? $right;
        return $left instanceof ColumnInterface
            && ($right instanceof ColumnInterface || $right instanceof ConstantInterface);
    }

    /**
     * @throws UnexpectedValueType
     * @throws \Exception
     */
    #[\Override]
    public function reverseConditions(): static
    {
        foreach (new \RecursiveIteratorIterator(new RecursiveIteratorByIterator($this->getIterator())) as $expression) {
            if ($expression instanceof ColumnInterface) {
                $expression->reverseForeign();
            }
        }

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $result                     = [];

        foreach ($this->childNodes as $expression) {
            $aql                    = $forResolved ? $expression->resolveNode()->getAql($forResolved) : $expression->getAql($forResolved);

            if (!empty($aql)) {

                if ($expression instanceof ConditionsInterface) {
                    $aql            = '(' . $aql . ')';
                }

                $result[]           = $aql;
            }
        }

        return \implode(' ' . $this->type . ' ', $result);
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return \implode(' ' . $this->type . ' ', $result);
    }

    #[\Override]
    public function shouldInheritContext(): bool
    {
        return true;
    }
}
