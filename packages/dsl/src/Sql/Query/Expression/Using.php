<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;

/**
 * DELETE a1, a2 FROM t1 AS a1 INNER JOIN t2 AS a2,
 * where a1, a2 are using Expression.
 */
class Using extends NodeAbstract
{
    /**
     * @var string[]
     */
    protected array $aliases;

    /**
     * @var string[]
     */
    protected array $resolvedAliases = [];

    /**
     * List of subjects.
     */
    public function __construct(string ...$aliases)
    {
        parent::__construct();

        $this->aliases              = $aliases;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->aliases === []) {
            return '';
        }

        $results = $this->aliases;

        return \implode(', ', $results);
    }

    public function resolveAlias(callable $resolver): void
    {
        if ($this->aliases === []) {
            return;
        }

        $this->resolvedAliases      = [];

        foreach ($this->aliases as $alias) {
            $this->resolvedAliases[] = $resolver($alias) ?? throw new TransformationException('Alias for entity ' . $alias . ' is not found');
        }
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->resolvedAliases === []) {
            return '';
        }

        $results                    = [];

        foreach ($this->resolvedAliases as $alias) {
            $results[]              = $this->escape($alias);
        }

        return \implode(', ', $results);
    }
}
