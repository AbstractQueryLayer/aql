<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

final class ResultNull extends ResultAbstract
{
    public function __construct()
    {
        $this->isFetching           = true;
        $this->results              = null;
    }

    public function affected(): int
    {
        return 0;
    }

    #[\Override]
    protected function realFetch(): ?array
    {
        return $this->results;
    }
}
