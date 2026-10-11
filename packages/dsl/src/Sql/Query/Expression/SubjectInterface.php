<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface SubjectInterface extends NodeInterface
{
    public const string USE_INDEX   = 'USE';

    public const string FORCE_INDEX = 'FORCE';

    public const string IGNORE_INDEX = 'IGNORE';

    public const array INDEX_HINTS  = [self::USE_INDEX, self::FORCE_INDEX, self::IGNORE_INDEX];

    /**
     * Returns name of a subject.
     */
    public function getSubjectName(): string;

    public function setSubjectName(string $subjectName): static;

    /**
     * Returns DataBase resolved name (example table name).
     */
    public function getResolvedName(): ?string;

    /**
     * Defined DataBase name for a subject.
     *
     * @return $this
     */
    public function setResolvedName(string $resolvedName): static;

    /**
     * Returns Alias for Subject.
     */
    public function getSubjectAlias(): string;

    /**
     * Defined subject alias.
     *
     *
     * @return $this
     */
    public function setSubjectAlias(string $alias): static;

    /**
     * Returns subject alias or Resolved Name.
     */
    public function getNameOrAlias(): string;

    /**
     * Index hints in the order they were added: [hint, index names], hint is one of INDEX_HINTS.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    public function getIndexHints(): array;

    /**
     * Adds `USE|FORCE|IGNORE INDEX (indexes)` after the table. Index names are those of the database, not of the
     * entity; the hint changes the plan, never the rows.
     *
     * @param string $hint one of INDEX_HINTS
     *
     * @return $this
     */
    public function addIndexHint(string $hint, string ...$indexes): static;

    /**
     * Removes every index hint: for a database without them.
     *
     * @return $this
     */
    public function clearIndexHints(): static;
}
