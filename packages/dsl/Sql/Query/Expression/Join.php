<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class Join extends NodeAbstract implements JoinInterface
{
    protected string $nodeName      = 'JOIN';

    protected string $alias         = '';

    protected bool $onlySubject     = false;

    /**
     * No print join type.
     */
    protected bool $withoutType     = false;

    /**
     * Insert into.
     */
    protected bool $insertInto      = false;

    public static function newFromSubquery(SubqueryInterface $subquery, string $joinType, string $alias): static
    {
        $join                       = new static($joinType, new Subject('', '', $alias));
        $join->childNodes[self::NODE_SUBQUERY] = $subquery->setParentNode($join);
        return $join;
    }

    public function __construct(protected string $joinType, Subject|string $subject, RelationInterface|null $relation = null)
    {
        parent::__construct();

        $this->childNodes           = [
            self::NODE_SUBQUERY     => null,
            self::NODE_SUBJECT      => \is_string($subject) ? new Subject($subject) : $subject,
            self::NODE_RELATION     => $relation?->setParentNode($this),
            self::NODE_CONDITIONS   => null,
            self::NODE_JOINS        => ((new NodeList())->defineDelimiter("\n"))->setParentNode($this),
        ];

        $this->childNodes[self::NODE_SUBJECT]->setParentNode($this);
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $join                       = match ($this->joinType) {
            self::FROM              => self::FROM,
            ''                      => 'INNER JOIN',
            default                 => $this->joinType . ' JOIN'
        };

        if ($this->insertInto) {
            $join                   = 'INTO';
        }

        $join                       = $this->withoutType ? '' : $join . ' ';

        if ($this->childNodes[self::NODE_SUBQUERY] !== null) {
            $aql                    = $join
                                    . $this->childNodes[self::NODE_SUBQUERY]->getAql($forResolved)
                                    . ' as ' . $this->childNodes[self::NODE_SUBJECT]->getNameOrAlias();
        } else {
            $aql                    = $join . $this->childNodes[self::NODE_SUBJECT]->getAql($forResolved);
        }

        $relations                  = $this->childNodes[self::NODE_RELATION]?->getAql($forResolved);
        $conditions                 = $this->childNodes[self::NODE_CONDITIONS]?->getAql($forResolved);
        $on                         = [];

        if ($relations !== null && $relations !== '') {
            $on[]                   = $relations;
        }

        if ($conditions !== null && $conditions !== '') {
            $on[]                   = $conditions;
        }

        if ($on !== []) {
            $aql                    .= ' ON (' . \implode(' AND ', $on) . ')';
        }

        $dependentJoins             = [];

        foreach ($this->childNodes[self::NODE_JOINS] as $join) {
            $dependentJoins[]       = $forResolved ? $join->resolveNode()->getAql($forResolved) : $join->getAql($forResolved);
        }

        if ($dependentJoins !== []) {

            if ($this->joinType === self::FROM) {
                $aql                .= ' ' . \implode(' ', $dependentJoins);
            } else {
                $aql                .= ' {' . \implode(' ', $dependentJoins) . '}';
            }
        }

        return $aql;
    }

    #[\Override]
    public function getJoinType(): string
    {
        return $this->joinType;
    }

    #[\Override]
    public function setJoinType(string $joinType): static
    {
        $this->joinType             = $joinType;

        return $this;
    }

    #[\Override]
    public function getSubject(): SubjectInterface
    {
        return $this->childNodes[self::NODE_SUBJECT];
    }

    #[\Override]
    public function getRelation(): ?RelationInterface
    {
        return $this->childNodes[self::NODE_RELATION];
    }


    #[\Override]
    public function setRelation(RelationInterface $relation): static
    {
        $this->childNodes[self::NODE_RELATION] = $relation->setParentNode($this);
        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setConditions(ConditionsInterface $conditions): static
    {
        $this->childNodes[self::NODE_CONDITIONS] = $conditions->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function getConditions(): ?ConditionsInterface
    {
        return $this->childNodes[self::NODE_CONDITIONS];
    }

    #[\Override]
    public function getAlias(): string
    {
        return $this->alias === '' ? $this->childNodes[self::NODE_SUBJECT]->getNameOrAlias() : $this->alias;
    }

    #[\Override]
    public function getChildJoins(): array
    {
        return $this->childNodes[self::NODE_JOINS]->getChildNodes();
    }

    #[\Override]
    public function hasChildJoins(): bool
    {
        return $this->childNodes[self::NODE_JOINS]->hasChildNodes();
    }

    #[\Override]
    public function getParentJoin(): JoinInterface|null
    {
        $parent                     = $this->getParentNode()?->getParentNode();

        if ($parent instanceof JoinInterface) {
            return $parent;
        }

        return null;
    }

    #[\Override]
    public function setAlias(string $alias): static
    {
        $this->alias                = $alias;

        return $this;
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function addChildNode(NodeInterface ...$nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof JoinInterface) {
                $this->addJoin($node);
            } else {
                throw new UnexpectedValueType('node', $node, JoinInterface::class);
            }
        }
    }

    /**
     * @return  $this
     */
    #[\Override]
    public function addJoin(JoinInterface $join, ?string $alias = null): static
    {
        $this->childNodes[self::NODE_JOINS][$alias ?? $join->getAlias()] = $join;
        return $this->needTransform();
    }

    #[\Override]
    public function applyDependentJoins(JoinInterface ...$joins): static
    {
        foreach ($joins as $join) {
            $this->addJoin($join, $join->getAlias() === '' ? $join->getSubject()->getSubjectName() : $join->getAlias());
        }

        return $this;
    }

    #[\Override]
    public function findJoin(string $subjectName, bool $forModification = false): ?JoinInterface
    {
        if ($subjectName === $this->childNodes[self::NODE_SUBJECT]?->getSubjectName()) {
            return $this;
        }

        if ($forModification) {
            $this->needTransform();
        }

        foreach ($this->childNodes[self::NODE_JOINS] as $join) {
            /* @var $join JoinInterface */
            if ($join->getSubject()->getSubjectName() === $subjectName) {
                return $join;
            }

            $result                 = $join->findJoin($subjectName, $forModification);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    #[\Override]
    public function isJoinExists(string $subjectName, ?string $parentSubjectName = null): bool
    {
        $join                   = $parentSubjectName !== null ? $this->findJoin($parentSubjectName) : $this;

        if ($join === null) {
            return false;
        }

        return $join->findJoin($subjectName) !== null;
    }

    #[\Override]
    public function isOnlySubject(): bool
    {
        return $this->onlySubject;
    }

    #[\Override]
    public function getSubquery(): ?SubqueryInterface
    {
        return $this->childNodes[self::NODE_SUBQUERY];
    }

    #[\Override]
    public function onlySubject(): static
    {
        $this->onlySubject          = true;
        return $this;
    }

    #[\Override]
    public function withoutType(): static
    {
        $this->withoutType          = true;
        return $this;
    }

    #[\Override]
    public function insertInto(): static
    {
        $this->insertInto           = true;
        return $this;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->joinType === self::SUBQUERY) {
            return null;
        }

        $type                       = match ($this->joinType) {
            self::LEFT, self::INNER, self::OUTER
                                    => $this->joinType . ' JOIN',
            default                 => $this->joinType
        };

        if ($this->insertInto) {
            $type                   = 'INTO';
        }

        if ($this->childNodes[self::NODE_SUBQUERY] !== null) {

            $result                 = $this->childNodes[self::NODE_SUBQUERY]->getResult()
                                      . ' as ' . $this->escape($this->childNodes[self::NODE_SUBJECT]->getNameOrAlias());

            if ($this->withoutType) {
                return $result;
            }

            return $type . ' ' . $result;
        }

        if ($this->onlySubject) {
            $joins                  = $this->generateJoins();

            if ($joins !== '' && $joins !== '0') {
                $joins              = ',' . $joins;
            }

            return $this->childNodes[self::NODE_SUBJECT]->getResult() . $joins;
        }

        $relations                  = '';

        if ($this->childNodes[self::NODE_RELATION] !== null) {
            $relations              = $this->childNodes[self::NODE_RELATION]->getResult();

            if (!empty($relations)) {
                $relations          = ' ON (' . $relations . ')';
            }
        }

        $result                     = $this->childNodes[self::NODE_SUBJECT]->getResult() . $relations;
        $childJoins                 = $this->generateJoins();

        if ($childJoins !== '') {
            $result                 .= "\n" . \trim((string) $childJoins);
        }

        if ($this->withoutType) {
            return $result;
        }

        return $type . ' ' . $result;
    }

    /**
     * Generate:
     * FROM table1 as alias1
     * JOIN table2 as alias2 ON (relations)
     * JOIN table3 as alias2 ON (relations)
     * JOIN table3 as alias2 ON (relations)
     */
    protected function generateJoins(): string
    {
        if ($this->childNodes[self::NODE_JOINS]->getChildNodes() === []) {
            return '';
        }

        $result                     = [];

        foreach ($this->childNodes[self::NODE_JOINS] as $join) {

            /* @var $join JoinInterface */
            if ($join->getJoinType() === self::UNION) {
                continue;
            }

            $sql                    = $join->getResult();

            if (!empty($sql)) {
                $result[]           = $sql;
            }
        }

        return ' ' . \implode("\n", $result);
    }

    /**
     * Generate only UNION joins.
     */
    public function generateUnions(): string
    {
        if ($this->childNodes[self::NODE_JOINS]->getChildNodes() === []) {
            return '';
        }

        $result                     = [];

        foreach ($this->childNodes[self::NODE_JOINS] as $join) {

            /* @var $join JoinInterface */
            if ($join->getJoinType() !== self::UNION) {
                continue;
            }

            $sql                    = $join->getResult();

            if (!empty($sql)) {
                $result[]           = $sql;
            }
        }

        return ' ' . \implode("\n", $result);
    }
}
