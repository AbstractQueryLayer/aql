<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Preprocessing;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Result\ResultInterface;

interface PreprocessedQueryInterface
{
    public function getUniqueKey(): string;

    public function wasPreprocessed(): bool;

    public function getQuery(): BasicQueryInterface;

    public function defineExecutor(callable $executor): void;

    public function executeQuery(): ResultInterface;
}
