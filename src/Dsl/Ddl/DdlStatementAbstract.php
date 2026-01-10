<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeAbstract;

abstract class DdlStatementAbstract extends NodeAbstract
{
    protected bool $isTransformed   = true;

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }
}
