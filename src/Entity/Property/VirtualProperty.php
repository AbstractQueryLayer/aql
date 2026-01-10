<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Exceptions\PropertyWrongUse;
use IfCastle\AQL\Executor\Helpers\ContextHelper;

/**
 * Computed property of an entity that does not exist in the Database
 * A property can play different roles depending on the context:
 * * result: SELECT virtual_property
 * * filter: SELECT * FROM ... WHERE virtual_property = value
 * * setter: INSERT ... SET virtual_property = value.
 *
 * and others.
 *
 */
class VirtualProperty extends PropertyAbstract
{
    protected bool $isVirtual       = true;

    /**
     * @var callable|null
     */
    protected $singleHandler;

    /**
     * @var callable[]
     */
    protected array $virtualHandlers = [];

    public function __construct(string $name, string $type = self::T_STRING, array $virtualHandlers = [])
    {
        parent::__construct($name, $type);
        $this->virtualHandlers      = \array_merge($this->virtualHandlers, $virtualHandlers);
    }

    /**
     * @return  $this
     */
    public function setSingleHandler(callable $handler): static
    {
        $this->singleHandler        = $handler;

        return $this;
    }

    public function forResult(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_TUPLE] = $handler;

        return $this;
    }

    public function forFilter(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_FILTER] = $handler;

        return $this;
    }

    public function forAssign(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_ASSIGN] = $handler;

        return $this;
    }

    public function forRelation(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_RELATIONS] = $handler;

        return $this;
    }

    public function forGroupBy(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_GROUP_BY] = $handler;

        return $this;
    }

    public function forOrderBy(callable $handler): static
    {
        $this->virtualHandlers[NodeContextInterface::CONTEXT_ORDER_BY] = $handler;

        return $this;
    }

    #[\Override]
    protected function init(): void
    {
        $this->virtualHandlers      = \array_merge($this->virtualHandlers, $this->defineHandlers());
    }

    protected function defineHandlers(): array
    {
        return [];
    }

    #[\Override]
    public function handle(PropertyContextInterface $context): void
    {
        /**
         * check the validity of using the property depending on the context.
         */
        $isAble                     = false;

        $contextName                = ContextHelper::resolveContextName($context->getCurrentNode());

        $this->handleBefore($context, $contextName);

        if (\is_callable($this->singleHandler)) {
            $handler                = $this->singleHandler;
            $isAble                 = $handler($context, $this);
        } else {
            $isAble                 = $this->applyVirtualHandler($context, $contextName);
        }

        if ($isAble === false) {
            $this->throwWrongUsing($context);
        }

        if (\is_callable($this->handlerAfter)) {
            $handler                    = $this->handlerAfter;

            $handler($context, $this, $contextName);
        }
    }

    #[\Override]
    public function throwWrongUsing(PropertyContextInterface $context): void
    {
        throw new PropertyWrongUse(
            $context->getCurrentEntity()->getEntityName(),
            $this->getName(), 'can not using virtual property in context ' . $context->getContextName()
        );
    }

    protected function applyVirtualHandler(PropertyContextInterface $context, string $contextName): bool
    {
        if (!empty($this->virtualHandlers[$contextName]) && \is_callable($this->virtualHandlers[$contextName])) {
            return $this->virtualHandlers[$contextName]($context);
        }

        return false;
    }
}
