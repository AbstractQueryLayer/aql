<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;

class Having extends Conditions
{
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = parent::getAql($forResolved);

        if ($aql === '') {
            return '';
        }

        return 'HAVING ' . $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = $this->generateResultForChildNodes();

        if ($result === []) {
            return '';
        }

        return 'HAVING ' . \implode(' ' . $this->type . ' ', $result);
    }
}
