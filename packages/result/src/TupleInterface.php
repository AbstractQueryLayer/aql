<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

interface TupleInterface extends ResultInterface, \IteratorAggregate, \Countable
{
    public function isTuple(): bool;

    public function toArray(): array;

    public function isSingleRow(): bool;

    public function isSingleColumn(): bool;

    public function columnToArray(string $key): array;

    public function map(callable $function): array;

    public function mapSingle(callable $function): mixed;

    /**
     * The method returns the tuple of selected columns.
     *
     *
     */
    public function selectColumns(string ...$columns): array;

    /**
     * The method returns the tuple of selected columns without keys.
     *
     *
     */
    public function selectColumnsWithoutKeys(string ...$columns): array;

    /**
     * Groups a tuple by the specified columns and returns an associative array
     * where the array key is the concatenated columns and the values are a key-matching list.
     * If $removeKeys is true, the keys of the resulting array will be removed also with hidden keys.
     *
     * @param    string[]   $columns
     *
     */
    public function selectAndGroupBy(array $columns, bool $removeKeys = true, string $keySeparator = ':'): array;

    public function firstToArray(): array;

    public function toKeyValue(?string $key = null, ?string $value = null): array;

    public function getFirstOrNull(): ?array;

    public function getFirstColumn(): mixed;

    public function getFirstColumnAsInt(): int;

    public function modify(array $data): static;

    public function modifyWith(callable $function, mixed $context = null): static;

    public function modifyColumn(string $key, callable $function, mixed $context = null): static;

    public function mergeGroupedRows(array $data, string $column, array $keys, mixed $default = null, string $keySeparator = ':'): static;

    public function getHiddenColumnsCount(): int;

    /**
     * Define the number of hidden columns from the end of the row.
     *
     *
     * @return $this
     */
    public function setHiddenColumns(int $hiddenColumnsCount): static;

    /**
     * Remove hidden columns from the tuple.
     *
     * @return $this
     */
    public function removeHiddenColumns(): static;
}
