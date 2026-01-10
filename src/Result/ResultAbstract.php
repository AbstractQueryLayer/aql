<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\TypeDefinitions\Value\ValueContainerInterface;

abstract class ResultAbstract implements
    ResultInterface,
    TupleInterface,
    InsertUpdateResultInterface,
    InsertUpdateResultSetterInterface,
    DisposableInterface
{
    protected ?array $results       = null;

    protected ?InsertUpdateResultInterface $insertUpdateResult = null;

    protected int $hiddenColumnsCount = 0;

    /**
     * Equal TRUE if a result should be single.
     */
    protected bool $isSingleRow     = false;

    /**
     * Equal TRUE if a result should be single first column.
     */
    protected bool $isSingleColumn  = false;

    protected bool $isFetching      = false;

    /**
     * @throws LogicalException
     */
    #[\Override]
    public function setInsertUpdateResult(InsertUpdateResultInterface $insertUpdateResult): static
    {
        if ($this->insertUpdateResult !== null) {
            throw new LogicalException('insertUpdateResult is already set!');
        }

        $this->insertUpdateResult   = $insertUpdateResult;

        return $this;
    }

    #[\Override]
    public function getLastPrimaryKey(): array|string|int|float|null
    {
        // If any rows affected, return null
        if ($this->getAffectedRows() === 0) {
            return null;
        }

        return $this->insertUpdateResult?->getLastPrimaryKey();
    }

    #[\Override]
    public function getLastDateTime(): ?\DateTimeImmutable
    {
        return $this->insertUpdateResult?->getLastDateTime();
    }

    #[\Override]
    public function getLastColumn(string $column): mixed
    {
        return $this->insertUpdateResult?->getLastColumn($column);
    }

    #[\Override]
    public function getLastRow(): ?array
    {
        return $this->insertUpdateResult?->getLastRow();
    }

    #[\Override]
    public function getInsertedRows(): array
    {
        return $this->insertUpdateResult?->getInsertedRows() ?? [];
    }

    #[\Override]
    public function getAffectedRows(): int
    {
        return $this->insertUpdateResult?->getAffectedRows() ?? 0;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        $results                    = $this->realFetch();

        if (!\is_array($results)) {
            $results                = [];
        }

        return new \ArrayIterator($results);
    }

    #[\Override]
    public function count(): int
    {
        $array                      = $this->realFetch();

        return \is_array($array) ? \count($array) : 0;
    }

    #[\Override]
    public function isTuple(): bool
    {
        return $this->insertUpdateResult === null;
    }

    #[\Override]
    public function isInsertUpdateResult(): bool
    {
        return $this->insertUpdateResult !== null;
    }

    /**
     * Convert results to array
     * Note!
     * If isSingleResult mode is TRUE, method should return only the first row as a result!
     */
    #[\Override]
    public function toArray(): array
    {
        if ($this->isSingleRow) {
            return $this->getFirstOrNull() ?? [];
        }

        return $this->realFetch();
    }

    /**
     * Returns TRUE if result.
     */
    #[\Override]
    public function isSingleRow(): bool
    {
        return $this->isSingleRow;
    }

    protected function asSingleRow(): static
    {
        $this->isSingleRow       = true;
        return $this;
    }

    #[\Override]
    public function isSingleColumn(): bool
    {
        return $this->isSingleColumn;
    }

    protected function asSingleColumn(): static
    {
        $this->isSingleColumn       = true;
        return $this;
    }

    #[\Override]
    public function map(callable $function): array
    {
        return \array_map($function, $this->realFetch() ?? []);
    }

    #[\Override]
    public function mapSingle(callable $function): mixed
    {
        $result                     = $this->getFirstOrNull();

        if ($result === null) {
            return null;
        }

        return $function($result);
    }

    #[\Override]
    public function columnToArray(string $key): array
    {
        $array                      = [];
        $result                     = $this->realFetch();

        if ($result === null || $result === []) {
            return [];
        }

        if (!\array_key_exists($key, $result[0])) {
            return [];
        }

        foreach ($result as $row) {
            $array[]                = $row[$key];
        }

        return $array;
    }

    #[\Override]
    public function selectColumns(string ...$columns): array
    {
        $array                      = [];
        $result                     = $this->realFetch();

        if ($result === null || $result === []) {
            return [];
        }

        $columns                    = \array_flip($columns);

        foreach ($result as $row) {
            $array[]                = \array_intersect_key($row, $columns);
        }

        return $array;
    }

    #[\Override]
    public function selectColumnsWithoutKeys(string ...$columns): array
    {
        $array                      = [];
        $result                     = $this->realFetch();

        if ($result === null || $result === []) {
            return [];
        }

        $columns                    = \array_flip($columns);

        foreach ($result as $row) {
            $array[]                = \array_values(\array_intersect_key($row, $columns));
        }

        return $array;
    }

    /**
     * @throws UnexpectedValue
     */
    #[\Override]
    public function selectAndGroupBy(array $columns, bool $removeKeys = true, string $keySeparator = ':'): array
    {
        if ($columns === []) {
            throw new UnexpectedValue('$columns', $columns, 'not should be empty');
        }

        $result                     = [];

        // Special case for single column
        if (\count($columns) === 1) {

            foreach ($this->realFetch() as $row) {

                if (false === \array_key_exists($columns[0], $row)) {
                    continue;
                }

                $key                = $row[$columns[0]];

                // Remove hidden columns
                if ($removeKeys && $this->hiddenColumnsCount > 0) {
                    $row            = \array_slice($row, 0, -$this->hiddenColumnsCount);
                }

                if ($removeKeys && \array_key_exists($columns[0], $row)) {
                    unset($row[$columns[0]]);
                }

                $result[$key][]     = $row;
            }

            return $result;
        }

        $columns                    = \array_flip($columns);

        foreach ($this->realFetch() as $row) {
            $key                    = \implode($keySeparator, \array_intersect_key($row, $columns));

            if ($key === '') {
                continue;
            }

            // Remove hidden columns
            if ($removeKeys && $this->hiddenColumnsCount > 0) {
                $row                = \array_slice($row, 0, -$this->hiddenColumnsCount);
            }

            if ($removeKeys) {
                $row                = \array_diff_key($row, $columns);
            }

            $result[$key][]         = $row;
        }

        return $result;
    }

    #[\Override]
    public function firstToArray(): array
    {
        $result                     = $this->realFetch();

        if ($result === null || $result === []) {
            return [];
        }

        return $this->columnToArray(\array_key_first($result[0]));
    }

    #[\Override]
    public function toKeyValue(?string $key = null, ?string $value = null): array
    {
        $array                      = [];
        $result                     = $this->realFetch();

        if ($result === null || $result === []) {
            return [];
        }

        if (\count($result[0]) < 2) {
            return [];
        }

        if ($key === null || $value === null) {
            [$key, $value]          = \array_keys($result[0]);
        }

        if (!\array_key_exists($key, $result[0]) || !\array_key_exists($value, $result[0])) {
            return [];
        }

        foreach ($result as $row) {
            $array[$row[$key]]      = $row[$value];
        }

        return $array;
    }

    /**
     * Return first row or null.
     */
    #[\Override]
    public function getFirstOrNull(): ?array
    {
        $results                    = $this->realFetch();

        return $results === null || $results === [] ? null : $results[0];
    }

    /**
     * Return first value as Int (0 - otherwise).
     *
     */
    #[\Override]
    public function getFirstColumn(): mixed
    {
        $results                    = $this->getFirstOrNull();

        if ($results === null || $results === []) {
            return null;
        }

        return \array_values($results)[0];
    }

    /**
     * Return first row as Int (0 - otherwise).
     */
    #[\Override]
    public function getFirstColumnAsInt(): int
    {
        $result                    = $this->getFirstColumn();

        return $result !== null ? (int) $result : 0;
    }

    #[\Override]
    public function modify(array $data): static
    {
        $this->isFetching           = true;
        $this->results              = $data;
        return $this;
    }

    /**
     * modifies results per row with a callback function.
     * Callback function should return a new row or null.
     *
     * @return  $this
     */
    #[\Override]
    public function modifyWith(callable $function, mixed $context = null): static
    {
        $results                    = [];

        foreach ($this->toArray() as $row) {
            $result                 = $function($row, $context);

            if (\is_array($result)) {
                $results[]          = $result;
            }
        }

        return $this->modify($results);
    }

    /**
     * Modifies the key in the result array. If the key is not specified, it will not be processed.
     *
     *
     * @return  $this
     */
    #[\Override]
    public function modifyColumn(string $key, callable $function, mixed $context = null): static
    {
        $this->realFetch();

        foreach ($this->results as &$row) {
            if (\array_key_exists($key, $row)) {
                $row[$key]              = $function($row[$key], $context);
            }
        }

        unset($row);

        return $this;
    }

    #[\Override]
    public function mergeGroupedRows(array $data, string $column, array $keys, mixed $default = null, string $keySeparator = ':'): static
    {
        $this->realFetch();

        $keys                       = \array_flip($keys);

        foreach ($this->results as &$row) {
            $key                    = \implode($keySeparator, \array_intersect_key($row, $keys));

            $row[$column] = \array_key_exists($key, $data) ? $data[$key] : $default;
        }

        unset($row);

        return $this;
    }

    #[\Override]
    public function getHiddenColumnsCount(): int
    {
        return $this->hiddenColumnsCount;
    }

    #[\Override]
    public function setHiddenColumns(int $hiddenColumnsCount): static
    {
        $this->hiddenColumnsCount   = $hiddenColumnsCount;
        return $this;
    }

    /**
     * The method removes hidden columns from the results that should not be displayed to the user.
     * Hidden columns must be placed at the beginning of the array-row.
     *
     * @return $this
     */
    #[\Override]
    public function removeHiddenColumns(): static
    {
        $offset                     = $this->hiddenColumnsCount;

        if ($offset === 0) {
            return $this;
        }

        $this->hiddenColumnsCount   = 0;

        $this->realFetch();

        foreach ($this->results as $i => $row) {
            $this->results[$i]      = \array_slice($row, 0, -$offset);
        }

        return $this;
    }

    #[\Override]
    public function finalize(): static
    {
        if ($this->hiddenColumnsCount > 0) {
            $this->removeHiddenColumns();
        }

        return $this;
    }

    /**
     * Apply definitions to result.
     *
     *
     */
    public function applyDefinitions(ResultToDtoInterface $query): ValueContainerInterface
    {
        return $query->resultToDto($this);
    }

    #[\Override]
    public function dispose(): void
    {
        $this->results              = null;
    }

    abstract protected function realFetch(): ?array;
}
