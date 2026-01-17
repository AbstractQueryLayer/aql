<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

/**
 * Raw SQL expression that will be substituted into the query as is.
 */
class RawSql extends NodeAbstract
{
    public function __construct(public readonly string $sql)
    {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new static($array['sql'] ?? '');
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return ['sql' => $this->sql];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '';
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return $this->sql;
    }
}
