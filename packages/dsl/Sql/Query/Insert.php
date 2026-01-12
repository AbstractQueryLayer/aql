<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class Insert extends QueryAbstract implements InsertInterface
{
    public static function entity(string $entity, AssignmentListInterface|array|null $values = null): static
    {
        return new static($entity, $values);
    }

    public function __construct(SubjectInterface|string $subject, ValueListInterface|AssignmentListInterface|array|null $values = null)
    {
        parent::__construct();

        if (\is_string($subject)) {
            $subject                = new Subject(\ucfirst($subject));
        }

        $this->queryAction          = self::ACTION_INSERT;
        $this->setFrom((new From($subject))->insertInto());

        if ($values instanceof ValueListInterface) {
            $this->setValueList($values);
        } elseif ($values instanceof AssignmentListInterface) {
            $this->setAssigmentList($values);
            $this->childNodes[self::NODE_ASSIGMENT_LIST]     = $values;
        } elseif (\is_array($values)) {
            $assignments            = new AssignmentList();

            foreach ($values as $key => $value) {
                if ($value instanceof Assign === false) {
                    $assignments->addAssignment(new Column($key), $value);
                } else {
                    $assignments->addAssign($value);
                }
            }

            $this->setAssigmentList($assignments);
        }
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        $query                      = new self($array[self::MAIN_ENTITY] ?? null);
        $query->childNodes          = ArrayTyped::unserializeList($array[self::CHILD_NODES] ?? []);
        return $query;
    }

    #[\Override]
    public function markAsReplace(): static
    {
        $this->queryAction          = QueryInterface::ACTION_REPLACE;

        return $this;
    }

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
    public function getAql(bool $forResolved = false): string
    {
        $query                      = [$this->queryAction];
        $query[]                    = $this->getFrom()->getAql($forResolved);
        $query[]                    = $this->getValueList()?->getAql($forResolved) ?? '';
        $query[]                    = $this->getAssigmentList()?->getAql($forResolved) ?? '';
        $query[]                    = $this->getFromSelect()?->getAql($forResolved) ?? '';
        $query[]                    = $this->onDuplicateKey()?->getAql($forResolved) ?? '';

        return \implode(' ', $query);
    }

    #[\Override]
    public function isInsert(): bool
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
