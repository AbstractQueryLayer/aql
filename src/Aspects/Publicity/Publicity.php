<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Publicity;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Publicity implements AspectDescriptorInterface
{
    final public const string TYPICAL_IS_PUBLISHED = 'is_published';

    public function __construct(public string $property) {}

    #[\Override]
    public function getAspectName(): string
    {
        return 'Publicity\\Publicity';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return [];
    }
}
