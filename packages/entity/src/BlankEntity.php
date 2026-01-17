<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

final class BlankEntity extends EntityAbstract
{
    public const string BLANK_ENTITY = 'blankEntity';

    #[\Override]
    protected function buildAspects(): void {}

    #[\Override]
    protected function buildProperties(): void {}
}
