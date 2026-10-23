<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\Count as CountFunction;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;

class Count extends Select
{
    public function __construct(string|JoinInterface $from, ConditionsInterface|array|Where|null $where = null, ?LimitInterface $limit = null)
    {
        parent::__construct($from, [new TupleColumn(new CountFunction(), self::ACTION_COUNT)], $where, $limit);

        $this->queryAction          = self::ACTION_COUNT;
    }

    /** `COUNT FROM ...`: the action implies the counted column, so the AQL omits it as the parser expects. */
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $childNodes                 = $this->childNodes;

        unset($childNodes[self::NODE_TUPLE], $childNodes[self::NODE_UNION]);

        $results                    = $this->nodesToAql($childNodes, ' ', $forResolved);

        return $results === '' ? '' : self::ACTION_COUNT . ' ' . $results;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $results                    = $this->generateResultForChildNodes();

        if ($results === []) {
            return '';
        }

        return self::ACTION_SELECT . ' ' . \implode(' ', $results);
    }
}
