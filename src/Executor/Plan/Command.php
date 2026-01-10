<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutor;
use IfCastle\DesignPatterns\ExecutionPlan\BeforeAfterExecutorInterface;
use IfCastle\DesignPatterns\ExecutionPlan\ExecutionPlanInterface;
use IfCastle\DesignPatterns\ExecutionPlan\WeakStaticClosureExecutor;
use IfCastle\DesignPatterns\Handler\HandlerWithHashInterface;
use IfCastle\DesignPatterns\Handler\WeakHandler;

class Command implements CommandInterface
{
    /**
     * @var callable|object|null
     */
    protected $handler;

    protected \WeakReference|null $context = null;

    protected (ExecutionPlanInterface & BeforeAfterExecutorInterface)|null $beforeAfterExecutor = null;

    public function __construct(protected string $stage, callable $handler)
    {
        $this->handler              = $handler;
    }

    #[\Override]
    public function getCommandStage(): string
    {
        return $this->stage;
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->executeHandler(...$args);

        return null;
    }

    #[\Override]
    public function getHandlerHash(): string|int|null
    {
        if ($this->handler instanceof HandlerWithHashInterface) {
            return $this->handler->getHandlerHash();
        }

        if (\is_object($this->handler)) {
            return \spl_object_id($this->handler);
        }

        return \spl_object_id($this);
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function executeHandler(?ExecutionContextInterface $context = null): void
    {
        if ($this->beforeAfterExecutor === null) {
            $this->executeCommandHandler($context);
            return;
        }

        try {
            $this->context          = \WeakReference::create($context);
            $this->beforeAfterExecutor->addHandler(new WeakHandler($this->handler))->executePlan();
        } finally {
            $this->context          = null;
        }
    }

    protected function executeCommandHandler(?ExecutionContextInterface $context = null): void
    {
        $handler                    = $this->handler;

        if ($handler instanceof ExecutionHandlerInterface) {
            $handler->executeHandler($context);
        } else {
            $handler($context);
        }
    }

    #[\Override]
    public function getBeforeAfterExecutor(): BeforeAfterExecutorInterface
    {
        if ($this->beforeAfterExecutor !== null) {
            return $this->beforeAfterExecutor;
        }

        $this->beforeAfterExecutor  = new BeforeAfterExecutor(
            new WeakStaticClosureExecutor(static fn(self $self, $handler, $stage) => $self->handleStage($handler, $stage), $this)
        );

        return $this->beforeAfterExecutor;
    }

    protected function handleStage(mixed $handler, string $stage): void
    {
        if ($handler instanceof ExecutionHandlerInterface) {
            $handler->executeHandler($this->context?->get());
        } elseif (\is_callable($handler)) {
            $handler($stage);
        }
    }

    #[\Override]
    public function dispose(): void
    {
        $this->handler              = null;
        $this->context              = null;
        $this->beforeAfterExecutor  = null;
    }
}
