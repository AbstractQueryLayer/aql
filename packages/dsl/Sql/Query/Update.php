<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class Update extends QueryAbstract implements UpdateInterface
{
    public static function entity(string $entity, array|Where|ConditionsInterface|null $where = null): static
    {
        return new static($entity, $where);
    }

    protected string $queryAction   = self::ACTION_UPDATE;

    public function __construct(JoinInterface|string                 $from,
        Where|ConditionsInterface|array|null $where     = null,
        ?LimitInterface                       $limit     = null)
    {
        parent::__construct();

        if (\is_string($from)) {
            $from                   = new From(new Subject(\ucfirst($from)));
        }

        $this->setFrom($from->onlySubject()->withoutType());

        if ($where !== null) {
            $this->setWhere($where);
        }

        if ($limit !== null) {
            $this->setLimit($limit);
        }
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        $query                      = new self($array[self::MAIN_ENTITY] ?? null);
        $query->childNodes          = ArrayTyped::unserializeList($array[self::CHILD_NODES] ?? []);
        return $query;
    }

    /**
     * Add an assign element to SET expression
     * Should be user for chain initialization.
     *
     *
     * @return  $this
     */
    #[\Override]
    public function assigns(Assign ...$assigns): static
    {
        if ($this->getAssigmentList() === null) {
            $this->setAssigmentList(new AssignmentList());
        }

        foreach ($assigns as $assign) {
            $this->getAssigmentList()->addAssign($assign);
        }

        return $this;
    }

    #[\Override]
    public function assign(string $column, int|bool|float|string|null $value): static
    {
        if ($this->getAssigmentList() === null) {
            $this->setAssigmentList(new AssignmentList());
        }

        $this->getAssigmentList()->assign($column, $value);

        return $this;
    }

    #[\Override]
    public function assignKeyValues(array $keyValues): static
    {
        $this->setAssigmentList(AssignmentList::fromKeyValue($keyValues));

        return $this;
    }

    #[\Override]
    public function isUpdate(): bool
    {
        return true;
    }

    #[\Override]
    public function isInsertUpdate(): bool
    {
        return true;
    }

    #[\Override]
    public function isModifying(): bool
    {
        return true;
    }
}
