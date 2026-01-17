<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;

/**
 * ## NodeOptions.
 *
 * A node that represents a list of string options, for example, in an SQL query:
 * CREATE option1, option2, option3 TABLE table_name.
 *
 */
class NodeOptions extends NodeAbstract
{
    /**
     * @var array<string>
     */
    protected array $options        = [];

    public function __construct(protected string $delimiter = ',', string ...$options)
    {
        parent::__construct();

        $this->options              = $options;
    }

    /**
     * @return array<string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isOption(string $option): bool
    {
        return \in_array($option, $this->options, true);
    }

    public function addOptions(string ...$options): static
    {
        $this->options              = \array_merge($this->options, $options);

        return $this;
    }

    public function removeOption(string $option): static
    {
        $this->options              = \array_filter($this->options, fn(string $o): bool => $o !== $option);

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->options === []) {
            return '';
        }

        $this->options              = \array_unique($this->options);

        return \implode($this->delimiter, \array_map(
            fn(string $option): string => \strtoupper($option),
            $this->options,
        ));
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        if ($this->options === []) {
            return '';
        }

        $this->options              = \array_unique($this->options);

        return \implode($this->delimiter, \array_map(
            fn(string $option): string => \strtoupper($option),
            $this->options,
        ));
    }
}
