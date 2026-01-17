<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\ResultComposer;

use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\Plan\ExecutionPlanInterface;
use IfCastle\AQL\Executor\Plan\ResultHandlerCallback;
use IfCastle\AQL\Executor\Plan\ResultHandlerInterface;
use IfCastle\AQL\Executor\Plan\ResultProcessingPlanInterface;
use IfCastle\AQL\Executor\Plan\ResultReaderCallback;
use IfCastle\AQL\Executor\Plan\ResultReaderInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * Compiles the final result of a query by column and primary key.
 */
class ComposeByColumnAndKey implements ResultReaderInterface, ResultHandlerInterface, DisposableInterface
{
    public static function compose(
        string                        $columnAlias,
        array                         $fromKeyAliases,
        array                         $toKeyAliases,
        ResultProcessingPlanInterface $processingPlan,
        ExecutionPlanInterface        $executionPlan
    ): static {
        $composer                   = new static($columnAlias, $fromKeyAliases, $toKeyAliases);

        $processingPlan->addResultReader(new ResultReaderCallback(static fn(ResultInterface $result) => $composer->readResult($result)));
        $executionPlan->addResultComposer(new ResultHandlerCallback(static fn(ExecutionContextInterface $context) => $composer->handleResult($context)));

        return $composer;
    }

    protected ?TupleInterface $result = null;

    public function __construct(protected string $columnAlias, protected array $fromKeyAliases, protected array $toKeyAliases) {}

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function readResult(ResultInterface $result): void
    {
        if ($result instanceof TupleInterface === false) {
            throw new UnexpectedValueType('$result', $result, TupleInterface::class);
        }

        $this->result               = $result;
    }

    #[\Override]
    public function handleResult(ExecutionContextInterface $context): void
    {
        if ($this->result === null) {
            return;
        }

        $mainTuple                  = $context->getResult();

        if ($mainTuple instanceof TupleInterface) {
            $mainTuple->mergeGroupedRows(
                $this->result->selectAndGroupBy($this->toKeyAliases),
                $this->columnAlias,
                $this->fromKeyAliases,
                []
            );
        }
    }

    #[\Override]
    public function dispose(): void
    {
        $this->result               = null;
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $parameter                  = $args[0] ?? null;

        if ($parameter instanceof ExecutionContextInterface) {
            $this->handleResult($parameter);
        } elseif ($parameter instanceof ResultInterface) {
            $this->readResult($parameter);
        }

        return null;
    }
}
