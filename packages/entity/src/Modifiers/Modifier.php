<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Modifiers;

use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\Plan\RowModifierInterface;

class Modifier implements ModifierInterface, RowModifierInterface
{
    /**
     * @var callable
     */
    protected $queryHandler;

    /**
     * @var callable
     */
    protected $resultHandler;

    /**
     * @inheritDoc
     */
    public function __construct(protected string $name, ?callable $resultHandler = null, ?callable $queryHandler = null)
    {
        if ($queryHandler === null && $resultHandler !== null) {
            $queryHandler           = static function (QueryInterface $query, NodeContextInterface $context) {
                $context->getResultProcessingPlan()->addRowModifier($this);
            };
        }

        $this->queryHandler         = $queryHandler;
        $this->resultHandler        = $resultHandler;
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->modifyResultRows(...$args);

        return null;
    }

    #[\Override]
    public function modifyResultRows(array &$rows, ExecutionContextInterface $context): void
    {
        if (!\is_callable($this->resultHandler)) {
            throw new QueryException([
                'template'          => 'The modifier {modifier} is not callable',
                'modifier'          => $this->name,
            ]);
        }

        $handler                    = $this->resultHandler;

        $handler($rows, $context);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function handleQuery(QueryInterface $query, NodeContextInterface $context): void
    {
        if (!\is_callable($this->queryHandler)) {
            throw new QueryException([
                'template'          => 'The modifier {modifier} is not callable',
                'modifier'          => $this->name,
            ]);
        }

        $handler                    = $this->queryHandler;

        $handler($query, $context);
    }

    public function getQueryHandler(): callable
    {
        return $this->queryHandler;
    }

    public function getResultHandler(): callable
    {
        return $this->resultHandler;
    }
}
