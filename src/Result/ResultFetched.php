<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

class ResultFetched extends ResultAbstract
{
    public function __construct(array $results)
    {
        $this->isFetching           = true;
        $this->results              = $results;
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
