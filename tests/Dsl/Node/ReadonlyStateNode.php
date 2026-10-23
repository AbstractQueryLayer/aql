<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

final class ReadonlyStateNode extends ReadonlyStateParent
{
    protected readonly int $count;

    protected readonly ReadonlyStateMode $mode;

    public static function make(string $label, int $count, ReadonlyStateMode $mode): self
    {
        $node                       = new self();
        $node->initLabel($label);
        $node->count                = $count;
        $node->mode                 = $mode;

        return $node;
    }

    public function getAql(bool $forResolved = false): string
    {
        return '';
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getMode(): ReadonlyStateMode
    {
        return $this->mode;
    }
}
