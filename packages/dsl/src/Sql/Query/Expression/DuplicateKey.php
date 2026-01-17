<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

class DuplicateKey extends AssignmentList implements DuplicateKeyInterface
{
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $aql                        = $this->childNodesToAql(', ');

        if ($aql === '') {
            return '';
        }

        return 'ON DUPLICATE KEY UPDATE ' . $aql;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $aql                        = \implode(', ', $this->generateResultForChildNodes());

        if ($aql === '') {
            return '';
        }

        return 'ON DUPLICATE KEY UPDATE ' . $aql;
    }
}
