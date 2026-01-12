<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Preprocessing;

interface PreprocessedQueryStorageInterface
{
    public function findPreprocessedQuery(string $uniqueKey): PreprocessedQueryInterface|null;

    public function storePreprocessedQuery(PreprocessedQueryInterface $preprocessedQuery): void;
}
