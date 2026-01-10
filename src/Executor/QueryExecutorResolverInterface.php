<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Entity\EntityInterface;

interface QueryExecutorResolverInterface
{
    /**
     * The method selects the request executor and generator based on the request type and entity type.
     *
     *
     */
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface;
}
