<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;

/**
 * WITH (Common Table Expressions).
 *
 * Supports recursive CTEs.
 */
class With extends QueryAbstract implements WithInterface
{
    protected string $queryAction   = self::ACTION_WITH;

    protected bool $isRecursive     = false;

    public function __construct(SubqueryInterface ...$subqueries)
    {
        parent::__construct();
        $this->defineSubqueries($subqueries);
    }

    #[\Override]
    public function getSubqueries(): array
    {
        return $this->childNodes[self::NODE_SUBQUERIES]?->getChildNodes() ?? [];
    }

    #[\Override]
    public function defineSubqueries(array $subqueries): static
    {
        $this->childNodes[self::NODE_SUBQUERIES] = new NodeList(...$subqueries)->defineDelimiter("\n");

        $this->childNodes[self::NODE_SUBQUERIES]->setParentNode($this);

        if ($subqueries !== []) {
            $subject                = $subqueries[0]->getFrom()->getSubject()->getSubjectName();
            $this->childNodes[self::NODE_FROM] = new From(new Subject($subject))->setParentNode($this);
        }

        return $this;
    }

    #[\Override]
    public function getDefaultCteName(): string|null
    {
        foreach ($this->getSubqueries() as $subquery) {
            if ($subquery instanceof SubqueryInterface) {
                return $subquery->getCteAlias();
            }
        }

        return null;
    }

    #[\Override]
    public function findSubqueryByName(string $cteName): SubqueryInterface|null
    {
        foreach ($this->getSubqueries() as $subquery) {
            if ($subquery->getCteAlias() === \strtolower($cteName)) {
                return $subquery;
            }
        }

        return null;
    }

    #[\Override]
    public function findCteNameByEntityName(string $entityName): string|null
    {
        foreach ($this->getSubqueries() as $expression) {
            if ($expression->getFrom()->getSubject()->getSubjectName() === $entityName) {
                return $expression->getCteAlias();
            }
        }

        return null;
    }

    #[\Override]
    public function isRecursive(): bool
    {
        return $this->isRecursive;
    }

    #[\Override]
    public function asRecursive(): static
    {
        $this->isRecursive          = true;

        return $this;
    }

    #[\Override]
    public function getQuery(): QueryInterface|null
    {
        return $this->childNodes[self::NODE_QUERY] ?? null;
    }

    #[\Override]
    public function defineQuery(QueryInterface $query): static
    {
        $this->childNodes[self::NODE_QUERY] = $query->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $recursive                  = $this->isRecursive ? ' RECURSIVE' : '';
        $result                     = [];

        foreach ($this->getSubqueries() as $expression) {
            $result[]               = $expression->getCteAlias() . ' AS ' . $expression->getAql($forResolved);
        }

        $with                       = 'WITH' . $recursive . ' ' . \implode(', ', $result);

        if ($this->getQuery() === null) {
            return $with;
        }

        return $with . ' ' . $this->getQuery()->getAql($forResolved);
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $recursive                  = $this->isRecursive ? ' RECURSIVE' : '';
        $result                     = [];

        foreach ($this->getSubqueries() as $expression) {
            $result[]               = $expression->getCteAlias() . ' AS ' . $expression->getResult();
        }

        $with                       = 'WITH' . $recursive . ' ' . \implode(', ', $result);

        if ($this->getQuery() === null) {
            return $with;
        }

        return $with . ' ' . $this->getQuery()->getResult();
    }
}
