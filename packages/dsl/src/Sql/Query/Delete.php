<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;

class Delete extends QueryAbstract
{
    public static function entity(string $entity, array|Where|ConditionsInterface|null $where = null): static
    {
        return (new static($entity, $where));
    }

    protected string $queryAction   = self::ACTION_DELETE;

    public function __construct(JoinInterface|string                 $from,
        Where|ConditionsInterface|array|null $where     = null,
        ?LimitInterface                       $limit     = null)
    {
        parent::__construct();

        if (\is_string($from)) {
            $from                   = new From(new Subject(\ucfirst($from)));
        }

        $this->setFrom($from);

        if ($where !== null) {
            $this->setWhere($where);
        }

        if ($limit !== null) {
            $this->setLimit($limit);
        }
    }

    #[\Override]
    public function isDelete(): bool
    {
        return true;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $this->refuseUnconditional();

        return parent::generateResult();
    }

    #[\Override]
    public function isModifying(): bool
    {
        return true;
    }
}
