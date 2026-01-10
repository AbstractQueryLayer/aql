<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

class Subquery extends Select implements SubqueryInterface
{
    /**
     * Specifies whether sampling constraints should be specified.
     */
    protected bool $returnOnlyOne   = false;

    protected bool $fromSelect      = false;

    protected string $cteAlias      = '';

    #[\Override]
    public function returnOnlyOne(): static
    {
        $this->returnOnlyOne        = true;
        return $this;
    }

    #[\Override]
    public function shouldReturnOnlyOne(): bool
    {
        return $this->returnOnlyOne;
    }

    #[\Override]
    public function isFromSelect(): bool
    {
        return $this->fromSelect;
    }

    #[\Override]
    public function asFromSelect(): static
    {
        $this->fromSelect           = true;
        return $this;
    }

    #[\Override]
    public function searchDerivedEntity(): string
    {
        $subquery                   = $this;

        do {
            $prevSubquery           = $subquery;
            $subquery               = $prevSubquery->getFrom()->getSubquery();
        } while ($subquery !== null);

        return $prevSubquery->getFrom()->getSubject()->getSubjectName();
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '(' . parent::getAql($forResolved) . ')';
    }

    #[\Override]
    public function getCteAlias(): string
    {
        return $this->cteAlias;
    }

    #[\Override]
    public function setCteAlias(string $alias): static
    {
        $this->cteAlias             = $alias;
        return $this;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $result                     = parent::generateResult();

        if ($result === '') {
            return '';
        }

        return '(' . $result . ')';
    }
}
