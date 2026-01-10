<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Storage\SqlStatementInterface;

final class PDOStatementAdapter implements SqlStatementInterface
{
    private \WeakReference|null $query = null;

    public function __construct(private readonly \PDOStatement $statement, QueryInterface $query)
    {
        $this->query                = \WeakReference::create($query);
    }

    #[\Override]
    public function getQuery(): string
    {
        return $this->statement->queryString;
    }

    public function getPdoStatement(): \PDOStatement
    {
        return $this->statement;
    }

    #[\Override]
    public function getParameterDefinitions(): array
    {
        // TODO: Implement getParameterDefinitions() method.
    }

    #[\Override]
    public function getParameterKeys(): array
    {
        // TODO: Implement getParameterKeys() method.
    }

    #[\Override]
    public function bindParameter(ConstantInterface $parameter): void
    {
        // TODO: Implement bindParameter() method.
    }
}
