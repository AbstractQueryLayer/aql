<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

/** A node whose parent keeps a private readonly property: it is set only in the scope of this class. */
abstract class ReadonlyStateParent extends NodeAbstract
{
    private readonly string $label;

    public function getLabel(): string
    {
        return $this->label;
    }

    protected function initLabel(string $label): void
    {
        $this->label                = $label;
    }
}
