<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

interface InsertUpdateResultInterface
{
    public function isInsertUpdateResult(): bool;

    public function getLastPrimaryKey(): array|string|int|float|null;

    public function getLastDateTime(): ?\DateTimeImmutable;

    public function getLastColumn(string $column): mixed;

    public function getLastRow(): ?array;

    public function getInsertedRows(): array;

    public function getAffectedRows(): int;
}
