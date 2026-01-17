<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

interface PoolTelemetryInterface
{
    public function registerBorrow(): void;

    public function registerReturn(): void;

    public function registerRebuild(): void;
}
