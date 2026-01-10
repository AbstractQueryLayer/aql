<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\DI\DisposableInterface;

final class ResultReaderCallback implements ResultReaderInterface, DisposableInterface
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback             = $callback;
    }

    #[\Override]
    public function readResult(ResultInterface $result): void
    {
        ($this->callback)($result);
    }

    #[\Override]
    public function dispose(): void
    {
        $this->callback             = null;
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->readResult(...$args);
        return null;
    }
}
