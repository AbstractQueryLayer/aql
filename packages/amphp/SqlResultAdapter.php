<?php

declare(strict_types=1);

namespace IfCastle\AQL\Amphp;

use Amp\Sql\SqlResult;
use IfCastle\AQL\Result\ResultAbstract;

class SqlResultAdapter extends ResultAbstract
{
    public function __construct(private SqlResult|null $sqlResult) {}

    #[\Override]
    public function dispose(): void
    {
        $this->sqlResult           = null;
        parent::dispose();
    }

    #[\Override]
    protected function realFetch(): ?array
    {
        if ($this->isFetching) {
            return $this->results;
        }

        if ($this->sqlResult === null) {
            return null;
        }

        $this->isFetching           = true;

        foreach ($this->sqlResult as $row) {
            $this->results[]        = $row;
        }

        return $this->results;
    }
}
