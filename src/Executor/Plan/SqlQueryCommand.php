<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class SqlQueryCommand extends QueryCommand implements SqlQueryCommandInterface
{
    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function getContainedSqlQuery(): QueryInterface
    {
        return $this->query instanceof QueryInterface ? $this->query
            : throw new UnexpectedValueType('$this->query', $this->query, QueryInterface::class);
    }
}
