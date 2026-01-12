<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

final class NullNode extends NodeAbstract
{
    protected bool $isTransformed = true;

    #[\Override]
    public function setParentNode(?NodeInterface $parentNode): static
    {
        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return '';
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return null;
    }
}
