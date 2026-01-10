<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Result\ResultInterface;

interface QueryableTelemetryInterface
{
    public function startQuery(StorageInterface $storage, string $query, object $context): void;

    public function endQuery(StorageInterface $storage, object $context, ?ResultInterface $result = null, ?\Throwable $throwable = null): void;

    public function startTransaction(): void;

    public function endTransaction(): void;
}
