<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\Exceptions\RequiredValueEmpty;

class Copy extends Insert
{
    protected string $queryAction   = self::ACTION_COPY;

    /**
     * @throws RequiredValueEmpty
     * @throws NodeException
     */
    public function __construct(string $subject, ?string $toSubject = null, Where|ConditionsInterface|array|null $where = null)
    {
        parent::__construct($subject);

        $this->setFromSelect(new Subquery($toSubject ?? $subject, null, $where));
    }

    #[\Override]
    public function isCopy(): bool
    {
        return true;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $results                    = $this->generateResultForChildNodes();

        if ($results === []) {
            return '';
        }

        return 'INSERT ' . \implode(' ', $results);
    }
}
