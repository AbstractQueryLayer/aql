<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

interface InsertUpdateResultSetterInterface
{
    public function setInsertUpdateResult(InsertUpdateResultInterface $insertUpdateResult): static;
}
