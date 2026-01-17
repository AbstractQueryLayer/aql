<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface ReaderWriterInterface
{
    public function useOnlyWriter(): bool;

    public function setUseOnlyWriter(): static;
}
