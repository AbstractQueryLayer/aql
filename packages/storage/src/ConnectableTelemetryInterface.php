<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface ConnectableTelemetryInterface
{
    public function registerConnect(StorageInterface $storage, array $attributes = []): void;

    public function registerDisconnect(StorageInterface $storage, array $attributes = []): void;

    public function registerError(StorageInterface $storage, \Throwable $throwable): void;
}
