<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\DesignPatterns\ScopeControl\ScopeMutableInterface;

interface BasicQueryInterface extends NodeInterface, ScopeMutableInterface
{
    final public const string ACTION        = 'a';

    final public const string MAIN_ENTITY   = 'm';

    /**
     * Returns name of query action.
     */
    public function getQueryAction(): string;

    public function getQueryStorage(): ?string;

    public function setQueryStorage(string $storage): static;

    public function isOption(string $name): bool;

    /**
     * Returns TRUE if The query changes the state of the database.
     */
    public function isSelect(): bool;

    public function isInsert(): bool;

    public function isCopy(): bool;

    public function isUpdate(): bool;

    public function isDelete(): bool;

    public function isInsertUpdate(): bool;

    public function isModifying(): bool;

    public function isResolvedAsSelect(): bool;

    public function isResolvedAsInsert(): bool;

    public function isResolvedAsClone(): bool;

    public function isResolvedAsUpdate(): bool;

    public function isResolvedAsDelete(): bool;

    public function isResolvedAsInsertUpdate(): bool;

    public function isResolvedAsModifying(): bool;

    /**
     * Returns the final action of the query
     * (the decision is made on the basis of the study of the substitute node).
     */
    public function getResolvedAction(): string;

    /**
     * Returns MAIN Entity of Query.
     */
    public function getMainEntityName(): string;

    public function setMainEntityName(string $entityName): static;

    /**
     * Try to find the main subject of a query.
     */
    public function findMainSubject(): ?SubjectInterface;

    /**
     * Returns Query options.
     */
    public function getQueryOptions(): QueryOptionsInterface;

    /**
     * Adds child nodes to the base nodes of a query.
     * Base query nodes are the first child nodes of the Query node, such as FROM, WHERE, ORDER BY, and others.
     *
     * For the operation to be successful,
     * the base query node must support the interface ChildNodeMutableInterface for adding child nodes.
     */
    public function addChildrenToBasicNode(string $nodeType, NodeInterface ...$nodes): void;

    /**
     * @return  ParameterInterface[]
     */
    public function getQueryParameters(): array;

    public function addQueryParameter(ParameterInterface ...$parameters): static;

    public function applyParameters(array $parameters): static;

    public function applyParameter(string $name, mixed $value): static;
}
