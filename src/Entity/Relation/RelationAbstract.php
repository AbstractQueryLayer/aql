<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Relation\RelationDirection;
use IfCastle\AQL\Dsl\Relation\RelationGeneralType;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;

abstract class RelationAbstract extends NodeAbstract implements RelationInterface
{
    /**
     * Returns the "reverse" type of relationship.
     *
     *
     */
    public static function reverseType(string $type): string
    {
        return match ($type) {

            self::INHERITANCE       => self::INHERITED_BY,
            self::INHERITED_BY      => self::INHERITANCE,

            self::SOFT_INHERITANCE  => self::SOFT_INHERITED_BY,
            self::SOFT_INHERITED_BY => self::SOFT_INHERITANCE,

            self::REFERENCE         => self::COLLECTION,
            self::COLLECTION        => self::REFERENCE,

            self::BELONGS_TO        => self::OWNS,
            self::OWNS              => self::BELONGS_TO,

            self::CHILD             => self::PARENT,
            self::PARENT            => self::CHILD,

            self::EXTENSION         => self::EXTENDED_BY,
            self::EXTENDED_BY       => self::EXTENSION,

            default                 => $type
        };
    }

    /**
     * Hi-level type of relation.
     */
    protected string $type;

    /**
     * @see isConsistentRelations
     */
    protected bool $isConsistent    = true;

    /**
     * The name of the entity that owns this relationship.
     */
    protected string $leftEntityName;

    /**
     * Name of entity to which there is a relationship.
     */
    protected string $rightEntityName;

    /**
     * Additional conditions.
     */
    protected ?ConditionsInterface $conditions = null;

    /**
     * Are these relationships Required (this means that entity A must always have entries in entity B)?
     * Usually this property is left NULL.
     * NULL means to look at the NULLABLE property of the field.
     */
    protected ?bool $isRequired     = null;

    /**
     * The entity must be present at least once for each entity on the right.
     */
    protected ?bool $isLeastOnce    = null;


    #[\Override]
    public function getRelationType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getRelationName(): string
    {
        return $this->rightEntityName;
    }

    #[\Override]
    public function getLeftEntityName(): string
    {
        return $this->leftEntityName;
    }

    #[\Override]
    public function getRightEntityName(): string
    {
        return $this->rightEntityName;
    }

    #[\Override]
    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    #[\Override]
    public function isLeastOnce(): ?bool
    {
        return $this->isLeastOnce;
    }

    #[\Override]
    public function getGeneralType(): ?RelationGeneralType
    {
        //
        // You should read this as
        // Another Entity related to this as "type" or over "type"
        // Example:
        // Category (other) related to article (this) as PARENT
        // ApiUsers related to Student over SOFT_INHERITANCE.
        //

        return match ($this->type) {
            // ONE TO ONE
            self::JOIN, self::INHERITANCE, self::INHERITED_BY, self::SOFT_INHERITANCE, self::SOFT_INHERITED_BY
            => RelationGeneralType::ONE_TO_ONE,
            // ONE_TO_MANY
            self::REFERENCE, self::OWNS, self::PARENT, self::EXTENDED_BY
            => RelationGeneralType::ONE_TO_MANY,

            // MANY_TO_ONE
            self::COLLECTION, self::BELONGS_TO, self::CHILD, self::EXTENSION
            => RelationGeneralType::MANY_TO_ONE,

            // MANY_TO_MANY
            self::ASSOCIATION       => RelationGeneralType::MANY_TO_MANY,

            // Can't define
            default                 => null
        };
    }

    #[\Override]
    public function direction(): RelationDirection
    {
        return match ($this->type) {

            // Example:
            // A ? B
            // where
            // A is leftEntity
            // B is rightEntity
            //
            // A JOIN to B means A depends on B.
            // A inherit B means A depends on B.
            // A reference to B means A depends on B.

            self::JOIN, self::INHERITANCE, self::SOFT_INHERITANCE,
            self::REFERENCE, self::BELONGS_TO, self::CHILD, self::COLLECTION, self::EXTENSION

            // leftEntity depends on RightEntity.
            => RelationDirection::FROM_RIGHT,

            // <A> inherited by B means B depends on A.
            // <A> extended by B means B depends on A.
            // <A> owns B means B depends on A.

            self::INHERITED_BY, self::SOFT_INHERITED_BY,
            self::EXTENDED_BY, self::PARENT, self::OWNS

            // RightEntity depends on LeftEntity
            => RelationDirection::FROM_LEFT,

            default                 => RelationDirection::TWO_SIDED
        };
    }

    #[\Override]
    public function isRightDependedOnLeft(): bool
    {
        return $this->direction() !== RelationDirection::FROM_RIGHT;
    }

    #[\Override]
    public function isLeftDependedOnRight(): bool
    {
        return $this->direction() !== RelationDirection::FROM_LEFT;
    }

    #[\Override]
    public function isConsistentRelations(): bool
    {
        return $this->isConsistent;
    }

    #[\Override]
    public function isNotConsistentRelations(): bool
    {
        return $this->isConsistent === false;
    }

    #[\Override]
    public function getAdditionalConditions(): ?ConditionsInterface
    {
        return $this->conditions;
    }

    #[\Override]
    public function getConstraints(): array
    {
        return [];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '';
    }

    /**
     * Generate On expression.
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return \implode(' AND ', $result);
    }

    /**
     * @return  $this
     */
    public function setIsConsistent(bool $isConsistent): static
    {
        $this->isConsistent         = $isConsistent;
        return $this;
    }


    public function setIsRequired(bool|null $isRequired): static
    {
        $this->isRequired           = $isRequired;
        return $this;
    }


    public function setIsLeastOnce(bool|null $isLeastOnce): static
    {
        $this->isLeastOnce          = $isLeastOnce;
        return $this;
    }

    #[\Override]
    public function generateConditionsAndApply(): void
    {
        $this->childNodes[]         = $this->generateConditions()->setParentNode($this);
    }

    #[\Override]
    public function cloneWithSubjects(?string $leftEntityName = null, ?string $rightEntityName = null): static
    {
        $clone                      = clone $this;
        $clone->leftEntityName      = $leftEntityName ?? $this->leftEntityName;
        $clone->rightEntityName     = $rightEntityName ?? $this->rightEntityName;

        return $clone;
    }
}
