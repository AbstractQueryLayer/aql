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

    /**
     * A DDL node renders without a transformation pass, so it is transformed from birth and holds nothing of a run:
     * only a substitution marks one.
     */
    #[\Override]
    protected function refuseTransformedSerialization(): void
    {
        if ($this->substitution !== null) {
            parent::refuseTransformedSerialization();
        }
    }
}
