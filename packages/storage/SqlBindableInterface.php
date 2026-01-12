<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

interface SqlBindableInterface
{
    public function bind(string $key, mixed $value): static;
}
