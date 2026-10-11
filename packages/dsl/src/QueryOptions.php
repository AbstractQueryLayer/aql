<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

class QueryOptions extends NodeAbstract implements QueryOptionsInterface
{
    protected string $nodeName      = QueryInterface::NODE_OPTIONS;

    #[\Override]
    public function findOption(string $optionName): ?QueryOptionInterface
    {
        foreach ($this->childNodes as $option) {
            /* @var $option QueryOptionInterface */
            if ($option->getOptionName() === $optionName) {
                return $option;
            }
        }

        return null;
    }

    #[\Override]
    public function getOption(string $optionName): QueryOptionInterface
    {
        return $this->findOption($optionName);
    }

    #[\Override]
    public function isOption(string $optionName): bool
    {
        $option                     = $this->findOption($optionName)?->resolveSubstitution();

        if ($option instanceof QueryOptionInterface) {
            // Only false and '' switch an option off; 0, 0.0 and '0' are values like any other.
            return !\in_array($option->getOptionValue(), [false, ''], true);
        }

        return false;
    }

    #[\Override]
    public function addOption(QueryOptionInterface|string $option, bool $isUnique = true, bool $isRedefine = true): static
    {
        if (\is_string($option)) {
            $option                 = new QueryOption($option);
        }

        if ($isUnique) {
            $oldOption              = $this->findOption($option->getOptionName());

            if ($oldOption !== null) {
                if ($isRedefine) {
                    $oldOption->setSubstitution($option);
                }

                return $this;
            }
        }

        $this->childNodes[]         = $option->setParentNode($this);

        return $this;
    }

    #[\Override]
    public function removeOption(string $optionName): static
    {
        foreach ($this->childNodes as $key => $option) {
            /* @var $option QueryOptionInterface */
            if ($option->getOptionName() === $optionName) {
                unset($this->childNodes[$key]);
            }
        }

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->childNodesToAql(' ');
    }
}
