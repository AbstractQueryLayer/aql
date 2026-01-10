<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Relation;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;

/**
 * ## Relation interface.
 *
 * Interface describes relations between entities.
 *
 * ### Why do we abandon the standard relationship?
 * In database theory, there are standard relationships between tables:
 * * one to one
 * * one to many
 * * many to many
 *
 * ### But here we do not use them, why?
 * Due to their low level of abstraction.
 *
 * The entity is much more than a database table.
 * This is an object (a class of objects)
 * that has relationships with other objects, and these relationships are more complicated.
 *
 * Each type of relationship solves its own unique high-level issue.
 * Be careful when choosing a relationship!
 *
 */
interface RelationInterface extends NodeInterface
{
    /**
     * ## SQL-Relation: one to one
     * Example: entity → entity_configuration.
     * @var string
     */
    final public const string JOIN  = 'join';

    /**
     * ## SQL-Relation: one to many.
     *
     * When one record reference to another table by primaryKey
     * Example:
     * * book reference to author (one book has one author)
     * But not article ⇒ category - it's OWNERSHIP (BELONGS_TO)
     *
     * ### difference with JOIN:
     * * author has many books
     * * a book has one author
     * * author not knows about books, because book reference to author.
     * @var string
     */
    final public const string REFERENCE = 'reference';

    /**
     * ## SQL-Relation: many to one
     * This relationship is inverse to REFERENCE.
     *
     * When one record included many other
     * Examples:
     * * one author reference to many books
     *
     * @var string
     */
    final public const string COLLECTION = 'collection';

    /**
     * ## SQL-Relation: many to many over addition table.
     *
     * Many to many over addition table.
     * Example:
     * * user has many groups, a group has many users
     *
     * @see https://en.wikipedia.org/wiki/Associative_entity
     * @var string
     */
    final public const string ASSOCIATION = 'association';

    /**
     * ## SQL-Relation: one to many.
     *
     * ### Example:
     * Group owns many users
     *
     * ### Difference from PARENT CHILD:
     * OWNS/BELONGS_TO is not a sole relationship.
     * PARENT/CHILD indicates the paramount importance of ownership.
     * @var string
     */
    final public const string OWNS = 'owns';

    /**
     * ## SQL-Relation: many to one.
     *
     * The opposite in meaning to OWN
     * ### Example:
     * Books belongs to author
     * Users belongs to group
     * @var string
     */
    final public const string BELONGS_TO = 'belongs_to';

    /**
     * ## SQL-Relation: one to many.
     *
     * Relation parent -> child, like category -> article
     * When parent has many children
     * @var string
     */
    final public const string PARENT = 'parent';

    /**
     * ## SQL-Relation: many to one.
     *
     * Relation like article -> category
     * When a child has one parent.
     *
     * ### Difference from PARENT CHILD:
     * OWNS/BELONGS_TO is not a sole owns.
     * PARENT/CHILD indicates the paramount importance of ownership.
     * @var string
     */
    final public const string CHILD = 'child';

    /**
     * Tree.
     * @var string
     */
    final public const string TREE = 'tree';

    /**
     * Nested relations.
     * @var string
     */
    final public const string NESTED = 'nested';

    /**
     * ## Relationships between entities to inherit
     * ## SQL-Relation: one to one
     * (like JOIN).
     * @var string
     */
    final public const string INHERITANCE = 'inheritance';

    /**
     * ## Reverse relation for INHERITANCE.
     * @var string
     */
    final public const string INHERITED_BY = 'inherited_by';

    /**
     * ## SQL-Relation: one to one.
     *
     * Relationships between entities to inherit,
     * However, when deleting an inherited entity, the parent remains,
     * and while at creation time, the parent may already exist.
     * @var string
     */
    final public const string SOFT_INHERITANCE = 'soft_inheritance';

    /**
     * Reverse relation for SOFT_INHERITANCE.
     * @var string
     */
    final public const string SOFT_INHERITED_BY = 'soft_inherited_by';

    /**
     * ## SQL-Relation: many to one.
     *
     * One entity extends the parent in a one-to-many relationship.
     * Example: Course and Course Languages.
     *
     * The difference from Parent -> Child is that the extension inherits all the fields of the parent entity.
     * The difference from INHERITANCE mode is that EXTENSION describes a one-to-many relations vs one-to-one.
     *
     * @var string
     */
    final public const string EXTENSION = 'extension';

    /**
     * ## SQL-Relation: one-to-many
     * Reverse relation for EXTENSION.
     * @var string
     */
    final public const string EXTENDED_BY = 'extended_by';

    /**
     * Relationship of a table to itself.
     * @var string
     */
    final public const string SELF_REFERENCE = 'self';

    /**
     * Returns unique relation name.
     */
    public function getRelationName(): string;

    public function getRelationType(): string;

    /**
     * Translate Hi-level relation to low-level one-to-one.
     */
    public function getGeneralType(): ?RelationGeneralType;

    /**
     * The method will return TRUE if the relationships between the entities are consistent,
     * i.e., the entities can be retrieved with ONE Query.
     *
     * False may mean that the entities are stored in different databases
     * or cannot be retrieved with a single query (for other reasons).
     */
    public function isConsistentRelations(): bool;

    public function isNotConsistentRelations(): bool;

    /**
     * Returns the direction of the dependency.
     * If entity B depends on entity A, the method will return FROM_LEFT
     * If entity A depends on entity B, the method will return FROM_RIGHT
     * If the entities depend on each other, the method will return TWO_SIDED.
     */
    public function direction(): RelationDirection;

    /**
     * Returns TRUE if rightEntity depends on leftEntity
     * else returns FALSE.
     */
    public function isRightDependedOnLeft(): bool;

    public function isLeftDependedOnRight(): bool;

    /**
     * Returns TRUE if relation is required.
     */
    public function isRequired(): ?bool;

    /**
     * Returns TRUE if the entity must be present at least once for each entity on the right.
     */
    public function isLeastOnce(): ?bool;

    public function getLeftEntityName(): string;

    public function getRightEntityName(): string;

    public function getAdditionalConditions(): ?ConditionsInterface;

    public function getConstraints(): array;

    public function reverseRelation(): ?static;

    /**
     * Clone relation with new subjects.
     */
    public function cloneWithSubjects(?string $leftEntityName = null, ?string $rightEntityName = null): static;

    /**
     * Generate WHERE expression from relation like:
     * leftSide.key = rightSide.key
     */
    public function generateConditions(): ConditionsInterface;

    public function generateConditionsAndApply(): void;
}
