<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface SubjectInterface extends NodeInterface
{
    /**
     * Returns name of a subject.
     */
    public function getSubjectName(): string;

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
}
