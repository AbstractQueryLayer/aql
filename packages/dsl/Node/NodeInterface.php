<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutorAwareInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;

interface NodeInterface extends \IteratorAggregate, BeforeAfterExecutorAwareInterface, ArraySerializableInterface
{
    final public const string CHILD_NODES = 'c';

    final public const string NODE_NAME = 'n';

    public function getNodeName(): string;

    /**
     * Returns TRUE if the NODE is logically empty and generates an empty AQL.
     * This should not be confused with a state where the node has no child nodes.
     *
     */
    public function isEmpty(): bool;

    /**
     * negation of isEmpty.
     *
     */
    public function isNotEmpty(): bool;

    public function getNodeContext(): object|null;

    public function setNodeContext(object $context): void;

    public function closestNodeContext(): object|null;

    public function closestParentContext(): object|null;

    public function getPath(): array;

    public function findClosestNodeByType(string $type): NodeInterface|null;

    /**
     * Normalizes the Node, calls the specified handler in the context if required.
     *
     * If the Node has a BeforeAfter execution plan,
     * the handler is placed in the main stage of the plan, and then the plan is executed.
     */
    public function transformWith(?callable $handler = null, ?object $context = null): void;

    /**
     * Marks the node as transpiled without calling the transpilation process.
     * If substitution is not defined, the node will be substituted with a null node.
     */
    public function asTransformed(): static;

    public function shouldInheritContext(): bool;

    public function hasChildNodes(): bool;

    public function getChildNodes(): array;

    public function getSubstitution(): ?NodeInterface;

    public function resolveSubstitution(): NodeInterface;

    public function setSubstitution(NodeInterface $node): static;

    public function substituteToNullNode(bool $markAsTransformed = true): void;

    public function getParentNode(): ?NodeInterface;

    public function setParentNode(NodeInterface|null $parentNode): static;

    /**
     * Returns the original node that has been replaced by the current node.
     */
    public function getOriginalNode(): ?NodeInterface;

    /**
     * Associates the current node with the node it replaces.
     *
     *
     * @return $this
     */
    public function setOriginalNode(NodeInterface $node): static;

    /**
     * Returns the path of the nodes that have been replaced by the current one from the root to the previous.
     */
    public function getOriginalPath(): array;

    /**
     * Returns Node by className or InterfaceName if it matches.
     *
     *
     */
    public function matchNodeByType(string $type): ?NodeInterface;

    /**
     * Returns last substitution or this node if substitution no exists.
     */
    public function resolveNode(): NodeInterface;

    /**
     * Sets a replacement for this Node via its copy.
     */
    public function substituteSelf(): NodeInterface;

    /**
     * @param    bool    $resetSubstitution     True if you need to reset the substitution
     *
     * @return  $this
     */
    public function needTransform(bool $resetSubstitution = false): static;

    public function isTransformed(): bool;

    public function isNotTransformed(): bool;

    /**
     * Call the handler after the node is transpiled.
     * If the node is already transpiled, the handler will be called immediately,
     * But if the node is not transpiled, the handler will be called after the normalization process.
     */
    public function afterTransformation(callable $handler): void;

    /**
     * Returns the result of the Node's transformation.
     */
    public function getResult(): mixed;

    /**
     * Returns the result of the Node's transformation as string.
     */
    public function getResultAsString(): string;

    /**
     * Defines the result of node transformation.
     *
     *
     * @return $this
     */
    public function setResult(mixed $result): static;

    /**
     * Callable handler with proto:
     * function(NodeI $node, bool $asString = false): mixed
     *
     *
     * @return $this
     */
    public function setResultGenerator(callable $resultGenerator): static;

    /**
     * @return $this
     */
    public function resetResultGenerator(): static;

    /**
     * Returns a string representation of the node as an Abstract Query Language.
     */
    public function getAql(bool $forResolved = false): string;
}
