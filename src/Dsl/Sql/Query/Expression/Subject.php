<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;

class Subject extends NodeAbstract implements SubjectInterface
{
    /**
     * Subject constructor.
     * @param   string              $subjectName    Entity name
     * @param   ?string             $resolvedName   Name of database table
     * @param   string              $subjectAlias   Alias
     */
    public function __construct(protected string $subjectName, protected ?string $resolvedName = '', protected string $subjectAlias = '')
    {
        parent::__construct();
    }

    #[\Override]
    public function getSubjectName(): string
    {
        return $this->subjectName;
    }

    #[\Override]
    public function getResolvedName(): ?string
    {
        return $this->resolvedName;
    }

    #[\Override]
    public function setResolvedName(string $resolvedName): static
    {
        $this->resolvedName         = $resolvedName;

        return $this;
    }

    #[\Override]
    public function getSubjectAlias(): string
    {
        return $this->subjectAlias;
    }

    #[\Override]
    public function setSubjectAlias(string $alias): static
    {
        $this->subjectAlias         = $alias;

        return $this;
    }

    #[\Override]
    public function getNameOrAlias(): string
    {
        return $this->subjectAlias !== '' ? $this->subjectAlias : ($this->resolvedName ?? '');
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->subjectName;
    }

    /**
     * Generate expression:
     * `table` AS `alias`
     * or
     * `table` if alias empty.
     *
     * @return string
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->resolvedName === null) {
            return '';
        }

        if ($this->subjectAlias === '') {
            return $this->escape($this->resolvedName);
        }

        return $this->escape($this->resolvedName) . ' as ' . $this->escape($this->subjectAlias);
    }
}
