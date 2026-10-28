<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Transaction\IsolationLevelEnum;

/**
 * The storage port of the job queue: AQL statements on the database that holds the Job and JobAttempt entities.
 *
 * An application implements it over its own connection handling, or uses AqlJobDatabase. Statements run one at a
 * time outside a transaction, or inside one on the instance handed to a transaction() callback.
 */
interface JobDatabaseInterface
{
    /**
     * Runs one AQL statement with {name} parameters bound from $parameters.
     *
     * @param array<string, mixed> $parameters
     * @throws DuplicateKeysException when an insert hits a unique key; the queue relies on it for dedup keys
     */
    public function query(string $aql, array $parameters = []): ResultInterface;

    /**
     * The rows of an AQL SELECT.
     *
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function select(string $aql, array $parameters = []): array;

    /**
     * Runs $operation with a database scoped to a new transaction and commits it; any throwable rolls it back and
     * is rethrown. Called on an instance that is itself scoped to a transaction, it throws \LogicException: the
     * queue never nests, and a caller that holds rows locked (JobQueue::lockActiveFor()) passes its scoped
     * instance to the queue instead.
     *
     * @param callable(JobDatabaseInterface): mixed $operation
     * @param ?IsolationLevelEnum $isolation the level of this transaction only; null keeps the server default
     * @return mixed what $operation returned
     */
    public function transaction(callable $operation, ?IsolationLevelEnum $isolation = null): mixed;
}
