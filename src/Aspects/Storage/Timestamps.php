<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class Timestamps implements AspectDescriptorInterface
{
    public const string CREATED         = 'created_at';

    public const string UPDATED         = 'updated_at';

    public const string PUBLISHED       = 'published_at';

    public const string DELETED         = 'deleted_at';

    protected array $options;

    public function __construct(string ...$options)
    {
        $this->options              = $options;
    }

    #[\Override]
    public function getAspectName(): string
    {
        return 'Storage\\Timestamps';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return $this->options;
    }
}
