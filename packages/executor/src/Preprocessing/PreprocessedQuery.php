<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Preprocessing;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class PreprocessedQuery implements PreprocessedQueryInterface, DisposableInterface
{
    private mixed $queryProvider;

    private BasicQueryInterface|null $query = null;

    private mixed $executor;

    public function __construct(callable $queryProvider, private readonly string $uniqueKey = '')
    {
        $this->queryProvider        = $queryProvider;
    }

    #[\Override]
    public function getUniqueKey(): string
    {
        return $this->uniqueKey;
    }

    #[\Override]
    public function wasPreprocessed(): bool
    {
        return $this->executor !== null;
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function getQuery(): BasicQueryInterface
    {
        if ($this->query === null && $this->queryProvider !== null) {
            $this->query            = ($this->queryProvider)();
            $this->queryProvider    = null;
        }

        if (false === $this->query instanceof BasicQueryInterface) {
            throw new UnexpectedValueType('query', $this->query, BasicQueryInterface::class);
        }

        return $this->query;
    }

    #[\Override]
    public function defineExecutor(callable $executor): void
    {
        if ($this->executor !== null) {
            throw new \LogicException('Executor already defined');
        }

        $this->executor              = $executor;
    }

    #[\Override]
    public function executeQuery(): ResultInterface
    {
        if ($this->executor === null) {
            throw new \LogicException('Executor not defined');
        }

        return ($this->executor)();
    }

    #[\Override]
    public function dispose(): void
    {
        $this->query                = null;
        $this->queryProvider        = null;
        $this->executor             = null;
    }
}
