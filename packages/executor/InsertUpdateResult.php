<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Sql\Helpers\InsertUpdateHelper;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Result\InsertUpdateResultInterface;

readonly class InsertUpdateResult implements InsertUpdateResultInterface
{
    public function __construct(
        protected ?EntityInterface      $entity                     = null,
        protected ?QueryInterface       $query                      = null,
        protected string|int|float|null $lastInsertedId             = null,
        protected int|null              $affected                   = null,
        protected \DateTimeImmutable|null $lastDateTime             = null
    ) {}

    #[\Override]
    public function isInsertUpdateResult(): bool
    {
        return true;
    }

    #[\Override]
    public function getLastPrimaryKey(): array|string|int|float|null
    {
        if ($this->entity === null || $this->query === null) {
            return $this->lastInsertedId;
        }

        $result                     = [];

        foreach ($this->entity->getPrimaryKey()->getKeyColumns() as $column) {
            $result[$column]        = InsertUpdateHelper::extractLastColumnValue($this->query, $column);
        }

        if ($this->lastInsertedId !== null && $result[\array_key_first($result)] === null) {
            $result[\array_key_first($result)]  = $this->lastInsertedId;
        }

        return $result;
    }

    #[\Override]
    public function getLastDateTime(): ?\DateTimeImmutable
    {
        return $this->lastDateTime;
    }

    #[\Override]
    public function getLastColumn(string $column): mixed
    {
        return InsertUpdateHelper::extractLastColumnValue($this->query, $column);
    }

    #[\Override]
    public function getLastRow(): ?array
    {
        $lastRow                    = null;

        foreach (InsertUpdateHelper::walkAssignedValues($this->query) as $row) {
            $lastRow                = $row;
        }

        if ($lastRow === null) {
            return null;
        }

        // Add last inserted id if defined
        if ($this->lastInsertedId !== null && ($autoInc = $this->entity->findAutoIncrement()) !== null) {
            $lastRow[$autoInc->getName()] = $this->lastInsertedId;
        }

        return $lastRow;
    }

    #[\Override]
    public function getInsertedRows(): array
    {
        return \iterator_to_array(InsertUpdateHelper::walkAssignedValues($this->query));
    }

    #[\Override]
    public function getAffectedRows(): int
    {
        return $this->affected ?? 0;
    }
}
