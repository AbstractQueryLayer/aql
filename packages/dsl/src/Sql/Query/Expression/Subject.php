<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\Exceptions\UnexpectedValue;

class Subject extends NodeAbstract implements SubjectInterface
{
    /**
     * @var list<array{0: string, 1: list<string>}>
     */
    protected array $indexHints     = [];

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

    /**
     * Names the subject's entity once the executor resolves it (a role join knows its entity only then).
     */
    public function setSubjectName(string $subjectName): static
    {
        $this->subjectName          = $subjectName;

        return $this;
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
    public function getIndexHints(): array
    {
        return $this->indexHints;
    }

    /**
     * @throws UnexpectedValue
     */
    #[\Override]
    public function addIndexHint(string $hint, string ...$indexes): static
    {
        if (!\in_array($hint, self::INDEX_HINTS, true)) {
            throw new UnexpectedValue('$hint', $hint, \implode('|', self::INDEX_HINTS));
        }

        if ($indexes === []) {
            throw new UnexpectedValue('$indexes', $indexes, 'at least one index name');
        }

        $this->indexHints[]         = [$hint, \array_values($indexes)];

        return $this;
    }

    #[\Override]
    public function clearIndexHints(): static
    {
        $this->indexHints           = [];

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->subjectName;
    }

    /**
     * Generate expression:
     * `table` AS `alias` USE INDEX (`index`)
     * the alias and the hints only when present.
     *
     * @return string
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->resolvedName === null) {
            return '';
        }

        $result                     = $this->escape($this->resolvedName);

        if ($this->subjectAlias !== '') {
            $result                 .= ' as ' . $this->escape($this->subjectAlias);
        }

        foreach ($this->indexHints as [$hint, $indexes]) {
            $names                  = \implode(', ', \array_map($this->escape(...), $indexes));
            $result                 .= ' ' . $hint . ' INDEX (' . $names . ')';
        }

        return $result;
    }
}
