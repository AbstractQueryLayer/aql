<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Transaction\IsolationLevelEnum;
use IfCastle\Jobs\JobDatabaseInterface;

/** A job database with no jobs, or one whose every statement fails when $failure is set. */
final class IdleDatabase implements JobDatabaseInterface
{
    public function __construct(private readonly ?\Throwable $failure = null) {}

    #[\Override]
    public function query(string $aql, array $parameters = []): ResultInterface
    {
        throw $this->failure ?? new \LogicException('An idle queue writes nothing');
    }

    #[\Override]
    public function select(string $aql, array $parameters = []): array
    {
        return $this->failure === null ? [] : throw $this->failure;
    }

    #[\Override]
    public function transaction(callable $operation, ?IsolationLevelEnum $isolation = null): mixed
    {
        return $operation($this);
    }
}
