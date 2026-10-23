<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Sql\Constant\BinaryString;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\ValueEscaperInterface;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutor;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutorInterface;
use IfCastle\DesignPatterns\ExecutionPlan\WeakStaticClosureExecutor;
use IfCastle\DesignPatterns\Handler\WeakStaticHandler;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\LogicalException;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;
use Traversable;

abstract class NodeAbstract implements NodeInterface, DisposableInterface
{
    protected string $nodeName      = '';

    /**
     * @var NodeInterface[]
     */
    protected array $childNodes     = [];

    #[NotSerialized]
    protected ?NodeInterface $substitution = null;

    #[NotSerialized]
    protected bool $isTransformed = false;

    /**
     * Query context.
     */
    #[NotSerialized]
    protected \WeakReference|null $context = null;

    /**
     * @var callable|null
     */
    #[NotSerialized]
    protected $resultGenerator;

    /**
     * @var mixed|null
     */
    #[NotSerialized]
    protected mixed $result         = null;

    #[NotSerialized]
    private ?\WeakReference $parentNode = null;

    #[NotSerialized]
    private ?\WeakReference $originalNode = null;

    #[NotSerialized]
    private BeforeAfterExecutorInterface|null $beforeAfterExecutor = null;

    /**
     * Basic Node constructor.
     */
    public function __construct(NodeInterface ...$nodes)
    {
        $this->childNodes           = $nodes;

        foreach ($nodes as $node) {
            $node->setParentNode($this);
        }
    }

    public function __clone(): void
    {
        $this->parentNode           = null;
        $this->originalNode         = null;

        if ($this->substitution !== null) {
            $this->substitution     = clone $this->substitution;
            $this->substitution->setOriginalNode($this);
        }

        foreach ($this->childNodes as $index => $childNode) {

            if ($childNode !== null) {
                $childNode          = (clone $childNode)->setParentNode($this);
            }

            $this->childNodes[$index] = $childNode;
        }
    }

    #[\Override]
    public function getBeforeAfterExecutor(): BeforeAfterExecutorInterface
    {
        if ($this->beforeAfterExecutor === null) {
            $this->beforeAfterExecutor = new BeforeAfterExecutor(new WeakStaticClosureExecutor(
                static fn(self $self, callable $handler) => $handler($self->context?->get(), $self),
                $this
            ));
        }

        return $this->beforeAfterExecutor;
    }

    #[\Override]
    public function isTransformed(): bool
    {
        return $this->isTransformed;
    }

    #[\Override]
    public function isNotTransformed(): bool
    {
        return $this->isTransformed === false;
    }

    #[\Override]
    public function afterTransformation(callable $handler): void
    {
        if ($this->isTransformed) {
            $handler($this->context?->get(), $this);
            return;
        }

        $this->getBeforeAfterExecutor()->addAfterHandler(
            new WeakStaticHandler(static function (self $self) use ($handler) {
                $handler($self->context?->get(), $self);
            }, $this)
        );
    }

    #[\Override]
    public function needTransform(bool $resetSubstitution = false): static
    {
        $this->isTransformed = false;
        $this->originalNode?->get()?->needNormalize();

        if ($resetSubstitution) {
            $this->substitution     = null;
        }

        return $this;
    }

    #[\Override]
    public function getNodeName(): string
    {
        return $this->nodeName;
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return false;
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return true;
    }

    #[\Override]
    public function getNodeContext(): object|null
    {
        return $this->context?->get();
    }

    #[\Override]
    public function setNodeContext(object $context): void
    {
        $this->context              = \WeakReference::create($context);
    }

    #[\Override]
    public function closestNodeContext(): object|null
    {
        $context                    = $this->context?->get();

        if ($context !== null) {
            return $context;
        }

        return $this->parentNode?->get()?->closestNodeContext();
    }

    #[\Override]
    public function closestParentContext(): object|null
    {
        return $this->parentNode?->get()?->closestNodeContext();
    }

    #[\Override]
    public function getPath(): array
    {
        $path                       = [];
        $currentNode                = $this;

        while ($currentNode !== null) {
            $path[]                 = $currentNode;
            $currentNode            = $currentNode->getParentNode();
        }

        return \array_reverse($path);
    }

    #[\Override]
    public function findClosestNodeByType(string $type): NodeInterface|null
    {
        $currentNode                = $this;

        while ($currentNode !== null) {
            if (\is_subclass_of($currentNode, $type)) {
                return $currentNode;
            }

            $currentNode            = $currentNode->getParentNode();
        }

        return null;
    }

    protected function executeNormalizationPlan(): void
    {
        try {
            $this->beforeAfterExecutor?->executePlan();
        } finally {

            $beforeAfterExecutor     = $this->beforeAfterExecutor;
            $this->beforeAfterExecutor = null;

            if ($beforeAfterExecutor instanceof DisposableInterface) {
                $beforeAfterExecutor->dispose();
            }
        }
    }

    #[\Override]
    public function transformWith(?callable $handler = null, ?object $context = null): void
    {
        if ($this->isTransformed()) {
            return;
        }

        $this->isTransformed = true;

        if ($this->getSubstitution() !== null) {
            $this->beforeAfterExecutor = null;
            return;
        }

        if ($context !== null) {
            $this->setNodeContext($context);
        }

        if ($this->beforeAfterExecutor === null) {

            if ($handler !== null) {
                $handler($this, $this->getNodeContext());
            }

            return;
        }

        if ($handler !== null) {
            $this->getBeforeAfterExecutor()->addHandler(
                new WeakStaticHandler(static function (self $node) use ($handler) {
                    if ($node->getSubstitution() === null) {
                        $handler($node, $node->getNodeContext());
                    }
                }, $this)
            );
        }

        $this->executeNormalizationPlan();
    }

    #[\Override]
    public function asTransformed(): static
    {
        $this->isTransformed       = true;
        $this->beforeAfterExecutor = null;

        if ($this->substitution === null) {
            $this->substituteToNullNode();
        }

        return $this;
    }

    #[\Override]
    public function shouldInheritContext(): bool
    {
        return false;
    }

    #[\Override]
    public function hasChildNodes(): bool
    {
        return $this->childNodes !== [];
    }

    #[\Override]
    public function getChildNodes(): array
    {
        return $this->childNodes;
    }

    #[\Override]
    public function getSubstitution(): ?NodeInterface
    {
        return $this->substitution;
    }

    #[\Override]
    public function resolveSubstitution(): NodeInterface
    {
        return $this->substitution?->resolveSubstitution() ?? $this;
    }

    /**
     * @throws NodeException
     */
    #[\Override]
    public function setSubstitution(NodeInterface $node): static
    {
        if ($this === $node) {
            throw new NodeException($this, 'Cannot substitute a node with itself');
        }

        $node->setOriginalNode($this);
        $this->substitution         = $node;
        $node->setParentNode($this->parentNode?->get());
        return $this;
    }

    #[\Override]
    public function substituteToNullNode(bool $markAsTransformed = true): void
    {
        $this->setSubstitution(new NullNode());

        if ($markAsTransformed) {
            $this->isTransformed    = true;
        }
    }

    #[\Override]
    public function getParentNode(): ?NodeInterface
    {
        return $this->parentNode?->get();
    }

    #[\Override]
    public function setParentNode(NodeInterface|null $parentNode): static
    {
        $this->parentNode           = $parentNode !== null ? \WeakReference::create($parentNode) : null;
        return $this;
    }

    #[\Override]
    public function getOriginalNode(): ?NodeInterface
    {
        return $this->originalNode?->get();
    }

    #[\Override]
    public function setOriginalNode(NodeInterface $node): static
    {
        $this->originalNode         = \WeakReference::create($node);
        return $this;
    }

    #[\Override]
    public function getOriginalPath(): array
    {
        $path                       = [];
        $currentNode                = $this;

        while ($currentNode !== null) {
            $path[]                 = $currentNode;
            $currentNode            = $currentNode->getOriginalNode();
        }

        return \array_reverse($path);
    }

    #[\Override]
    public function matchNodeByType(string $type): ?NodeInterface
    {
        return $this instanceof $type ? $this : $this->substitution?->matchNodeByType($type);
    }

    #[\Override]
    public function resolveNode(): NodeInterface
    {
        return $this->substitution !== null ? $this->substitution->resolveNode() : $this;
    }

    #[\Override]
    public function substituteSelf(): NodeInterface
    {
        $this->substitution         = clone $this;

        return $this->substitution;
    }

    #[\Override]
    public function getResult(): mixed
    {
        if ($this->substitution !== null) {
            return $this->substitution->getResult();
        }

        if ($this->resultGenerator !== null) {
            return \call_user_func($this->resultGenerator, $this);
        }

        if ($this->result === null) {
            return $this->generateResult();
        }

        return $this->result;
    }

    #[\Override]
    public function getResultAsString(): string
    {
        $result                     = $this->getResult();

        if ($result === null) {
            return '';
        }

        return (string) $result;
    }

    #[\Override]
    public function setResult(mixed $result): static
    {
        $this->result               = $result;
        return $this;
    }

    #[\Override]
    public function setResultGenerator(callable $resultGenerator): static
    {
        $this->resultGenerator      = $resultGenerator;
        return $this;
    }

    #[\Override]
    public function resetResultGenerator(): static
    {
        $this->resultGenerator      = null;
        return $this;
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->childNodes);
    }

    /**
     * Rebuilds a node written by toArray(), with its subtree; the node and its children are not transformed and
     * carry no context.
     *
     * @throws \IfCastle\Exceptions\UnSerializeException
     */
    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        unset($array[ArrayTyped::TYPE]);

        $node                       = NodeState::create(static::class, $array, $validator);

        foreach ($node->childNodes as $childNode) {
            $childNode?->setParentNode($node);
        }

        return $node;
    }

    /**
     * Writes the node and its subtree as plain values (NodeState): a query parsed or built once can be stored and
     * rebuilt by fromArray() instead of being parsed again.
     *
     * The array is a cache, not a durable format: it names the classes and properties of the code that wrote it and
     * is read only by that code. Parameter values already applied are written with the query. Strings are written
     * as they are, so a binary value needs an encoding that keeps bytes (serialize(), not JSON).
     *
     * @throws LogicalException when the node has been transformed for a run, since it then holds the run's entities
     *                          and context, or holds a value that cannot be written, such as a closure
     */
    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        $this->refuseTransformedSerialization();

        return NodeState::read($this, $validator);
    }

    /** @throws LogicalException when the node has been transformed for a run (see toArray()) */
    protected function refuseTransformedSerialization(): void
    {
        if ($this->isTransformed || $this->substitution !== null) {
            throw new LogicalException(
                'A transformed node cannot be serialized: ' . static::class . '; serialize the query before it runs'
            );
        }
    }

    #[\Override]
    public function dispose(): void
    {
        $this->resultGenerator      = null;
        $this->originalNode         = null;
        $this->parentNode           = null;

        $childNodes                 = $this->childNodes;
        $this->childNodes           = [];

        foreach ($childNodes as $childNode) {
            if ($childNode instanceof DisposableInterface) {
                $childNode->dispose();
            }
        }

        $this->substitution?->dispose();
        $this->substitution         = null;
    }

    /**
     * Convert Nodes to AQL.
     *
     * @param NodeInterface[]|null $nodes
     */
    protected function nodesToAql(?array $nodes = null, string $delimiter = '', bool $forResolved = false): string
    {
        $results                    = [];

        foreach ($nodes ?? [] as $node) {

            $aql                    = $forResolved ? $node?->resolveNode()->getAql($forResolved) : $node?->getAql($forResolved);

            if (self::isNotBlank($aql)) {
                $results[]          = $aql;
            }
        }

        if ($results === []) {
            return '';
        }

        return \implode($delimiter, $results);
    }

    /**
     * Convert child nodes to AQL.
     */
    protected function childNodesToAql(string $delimiter = '', bool $forResolved = false): string
    {
        return $this->nodesToAql($this->childNodes, $delimiter, $forResolved);
    }

    protected function generateResult(): mixed
    {
        return \implode(' ', $this->generateResultForChildNodes());
    }

    protected function generateResultForChildNodes(): array
    {
        $results                    = [];

        foreach ($this->childNodes as $childNode) {

            if ($childNode instanceof NodeInterface) {
                $result             = $childNode->getResult();

                if (self::isNotBlank($result)) {
                    $results[]      = $result;
                }
            }
        }

        return $results;
    }

    /**
     * A rendered part counts as present unless it is null, an empty string, an empty array or false.
     * The number 0 and the string '0' are present: they are the SQL constant 0, not a missing argument.
     */
    protected static function isNotBlank(mixed $part): bool
    {
        return $part === 0 || $part === '0' || !empty($part);
    }

    /**
     * Returns current query context.
     */
    protected function getContext(): ?object
    {
        return $this->context?->get();
    }

    /**
     * @throws TransformationException
     */
    protected function throwOutOfHandled(): void
    {
        if ($this->isTransformed === false) {
            throw new TransformationException([
                'template'          => 'Attempting to generate an unhandled Node {node} (need to call transformNode). {aql}',
                'node'              => static::class,
                'aql'               => $this->getAql(),
            ]);
        }
    }

    protected function quote(mixed $name): string
    {
        $context                    = $this->getContext();

        if ($context instanceof ValueEscaperInterface) {
            return $context->quote($name);
        }

        if ($name instanceof BinaryString) {
            return $name->toHexLiteral();
        }

        return '\'' . \addslashes((string) $name) . '\'';
    }

    protected function escape(string $name): string
    {
        $context                    = $this->getContext();

        if ($context instanceof ValueEscaperInterface) {
            return $context->escape($name);
        }

        return '`' . $name . '`';
    }
}
