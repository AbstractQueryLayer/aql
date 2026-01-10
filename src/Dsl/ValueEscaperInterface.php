<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

interface ValueEscaperInterface
{
    public function quote(mixed $value): string;

    public function escape(string $value): string;
}
