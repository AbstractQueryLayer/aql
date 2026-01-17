<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\ChildNodeMutableInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

interface JoinInterface extends NodeInterface, ChildNodeMutableInterface
{
    /**
     * @var string
     */
    public const string INNER       = 'INNER';

    /**
     * @var string
     */
    public const string LEFT        = 'LEFT';

    /**
     * @var string
     */
    public const string RIGHT       = 'RIGHT';

    /**
     * @var string
     */
    public const string OUTER       = 'OUTER';

    /**
     * @var string
     */
    public const string UNION       = 'UNION';

    /**
     * @var string
     */
    public const string SUBQUERY    = 'SUBQUERY';

    /**
     * @var string
     */
    public const string QUERY       = 'QUERY';

    /**
     * @var string
     */
    public const string FROM        = 'FROM';

    public const string NODE_RELATION   = 'relation';

    public const string NODE_CONDITIONS = 'conditions';

    public const string NODE_SUBQUERY   = 'subquery';

    public const string NODE_SUBJECT    = 'subject';

    public const string NODE_JOINS      = 'joins';

    public function getJoinType(): string;

    public function setJoinType(string $joinType): static;

    public function getAlias(): string;

    public function setAlias(string $alias): static;

    public function getSubject(): SubjectInterface;

    public function getRelation(): ?RelationInterface;

    public function setRelation(RelationInterface $relation): static;

    public function setConditions(ConditionsInterface $conditions): static;

    public function getConditions(): ?ConditionsInterface;

    public function getChildJoins(): array;

    public function hasChildJoins(): bool;

    public function getParentJoin(): JoinInterface|null;

    public function addJoin(JoinInterface $join, ?string $alias = null): static;

    public function applyDependentJoins(JoinInterface ...$joins): static;

    /**
     * Try to find join by subject Name.
     * search goes through all child nodes.
     *
     *
     */
    public function findJoin(string $subjectName, bool $forModification = false): ?JoinInterface;

    /**
     * Returns TRUE if Join exists within the parent.
     *
     *
     */
    public function isJoinExists(string $subjectName, ?string $parentSubjectName = null): bool;

    public function isOnlySubject(): bool;

    /**
     * Returns a subquery if the JOIN is a Derived entity.
     * @see https://dev.mysql.com/doc/refman/8.0/en/derived-tables.html
     */
    public function getSubquery(): ?SubqueryInterface;

    public function onlySubject(): static;

    public function withoutType(): static;

    public function insertInto(): static;
}
