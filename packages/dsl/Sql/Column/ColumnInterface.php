<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Column;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\TypeDefinitions\DefinitionInterface;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;

/**
 * ## Column
 * Reference to Field of Entity.
 */
interface ColumnInterface extends NodeInterface
{
    public function getEntityName(): ?string;

    public function setEntityName(string $name): static;

    public function getSubject(): ?string;

    public function setSubject(string $subject): static;

    public function getSubjectAlias(): ?string;

    public function setSubjectAlias(string $subjectAlias): static;

    public function getColumnName(): string;

    /**
     * Returns database field name associated with column.
     */
    public function getFieldName(): ?string;

    /**
     * Returns true if the entity property reference is external.
     */
    public function isForeign(): bool;

    /**
     * reverse the value of isForeign.
     *
     * @return $this
     */
    public function reverseForeign(): static;

    /**
     * Returns true if this column is a placeholder.
     */
    public function isPlaceholder(): bool;

    /**
     * Makes this column an empty placeholder.
     *
     * @return $this
     */
    public function markAsPlaceholder(): static;

    /**
     * @return $this
     */
    public function setFieldName(string $fieldName): static;

    public function getDefinition(): ?DefinitionInterface;

    public function setDefinition(DefinitionMutableInterface $definition): static;

    /**
     * Returns TRUE if $column is Equivalent to this.
     *
     */
    public function isEqual(string|ColumnInterface $column): bool;
}
