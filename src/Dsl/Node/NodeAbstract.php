<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\ValueEscaperInterface;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutor;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutorInterface;
use IfCastle\DesignPatterns\ExecutionPlan\WeakStaticClosureExecutor;
use IfCastle\DesignPatterns\Handler\WeakStaticHandler;
use IfCastle\DI\DisposableInterface;
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

    protected ?NodeInterface $substitution = null;

    protected bool $isTransformed = false;

    /**
     * Query context.
     */
    protected \WeakReference|null $context = null;

    /**
     * @var callable|null
     */
    protected $resultGenerator;

    /**
     * @var mixed|null
     */
    protected mixed $result         = null;

    private ?\WeakReference $parentNode = null;

    private ?\WeakReference $originalNode = null;

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
     * @param    ArraySerializableValidatorInterface|null    $validator        *
     *
     * @throws \IfCastle\Exceptions\UnSerializeException
     */
    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new static(...ArrayTyped::unserializeList($array[self::CHILD_NODES] ?? null, $validator));
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [self::NODE_NAME => $this->nodeName, self::CHILD_NODES => ArrayTyped::serializeList($validator, ...$this->childNodes)];
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

            if (!empty($aql)) {
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

                if (!empty($result)) {
                    $results[]      = $result;
                }
            }
        }

        return $results;
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
