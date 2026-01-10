<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\Result\ResultAbstract;

class PDOResult extends ResultAbstract
{
    public function __construct(protected ?\PDOStatement $pdoStatement) {}

    public function affected(): int
    {
        if ($this->pdoStatement === null) {
            return 0;
        }

        return $this->pdoStatement->rowCount();
    }

    #[\Override]
    public function count(): int
    {
        if ($this->pdoStatement === null) {
            return 0;
        }

        return $this->pdoStatement->rowCount();
    }

    #[\Override]
    public function dispose(): void
    {
        $this->pdoStatement         = null;
        parent::dispose();
    }

    #[\Override]
    protected function realFetch(): ?array
    {
        if ($this->isFetching) {
            return $this->results;
        }

        $this->isFetching           = true;
        $this->results              = $this->pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
        $this->pdoStatement         = null;

        return $this->results;
    }
}
