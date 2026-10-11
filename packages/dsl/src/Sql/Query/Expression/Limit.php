<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

class Limit extends NodeAbstract implements LimitInterface
{
    final public const string LIMIT = 'l';

    final public const string OFFSET = 'o';

    /**
     * Limit constructor.
     */
    public function __construct(protected int $limit = 0, protected int $offset = 0)
    {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new static($array[self::LIMIT] ?? 0, $array[self::OFFSET] ?? 0);
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return parent::toArray($validator) + [self::LIMIT => $this->limit, self::OFFSET => $this->offset];
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->limit === 0;
    }

    #[\Override]
    public function isNotEmpty(): bool
    {
        return $this->limit > 0;
    }

    #[\Override]
    public function getOffset(): int
    {
        return $this->offset;
    }

    #[\Override]
    public function getLimit(): int
    {
        return $this->limit;
    }

    #[\Override]
    public function setOffset(int $offset): static
    {
        $this->offset               = $offset;
        return $this;
    }

    #[\Override]
    public function setLimit(int $limit): static
    {
        $this->limit                = $limit;
        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->limit === 0) {
            return '';
        }

        return 'LIMIT ' . $this->offset . ', ' . $this->limit;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->limit === 0) {
            return '';
        }

        if ($this->offset === 0) {
            return 'LIMIT ' . $this->limit;
        }

        return \sprintf('LIMIT %s,%s', $this->offset, $this->limit);
    }
}
