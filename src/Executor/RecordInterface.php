<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Select;

interface RecordInterface
{
    public static function count(AqlExecutorInterface $aqlExecutor, array $filters = []): int;

    public static function fetchOne(AqlExecutorInterface $aqlExecutor, array $filters = []): static;

    public static function findOne(AqlExecutorInterface $aqlExecutor, array $filters = []): ?static;

    public static function fetchById(AqlExecutorInterface $aqlExecutor, string|int|float $id): static;

    public static function findById(AqlExecutorInterface $aqlExecutor, string|int|float $id): ?static;

    /**
     *
     * @return   static[]
     */
    public static function fetch(AqlExecutorInterface $aqlExecutor, array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    public static function selectCount(array $filters = []): Select;

    /**
     * Build a select query by filters, order by, limit and offset.
     *
     * @param    array<string, NodeInterface|scalar> $filters Key-value pairs of filters
     * @param    array<string, bool>                 $orderBy Key-value pairs of order by
     * @param    int|null                            $limit   Limit
     * @param    int|null                            $offset  Offset
     */
    public static function select(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): Select;

    public function insert(AqlExecutorInterface $aqlExecutor): static;

    public function update(AqlExecutorInterface $aqlExecutor): static;

    public function delete(AqlExecutorInterface $aqlExecutor): static;
}
