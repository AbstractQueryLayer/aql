<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\ActivationStatus;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class ActivationStatus implements AspectDescriptorInterface
{
    public const string IS_ENABLED = 'isEnabled';

    public function __construct(public readonly string $propertyName = self::IS_ENABLED) {}

    #[\Override]
    public function getAspectName(): string
    {
        return 'ActivationStatus\\ActivationStatus';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return [];
    }
}
