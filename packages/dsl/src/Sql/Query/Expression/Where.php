<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

class Where extends Conditions
{
    protected string $nodeName      = QueryInterface::NODE_WHERE;

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = parent::getAql($forResolved);

        if ($aql === '') {
            return '';
        }

        return 'WHERE ' . $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return 'WHERE ' . \implode(' ' . $this->type . ' ', $result);
    }
}
