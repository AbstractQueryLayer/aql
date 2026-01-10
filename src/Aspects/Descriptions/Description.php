<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Descriptions;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Description implements AspectDescriptorInterface
{
    final public const string TITLE        = 'title';

    final public const string NAME         = 'name';

    final public const string DESCRIPTION  = 'description';

    /**
     * @var string[]
     */
    protected array $options;

    public function __construct(public readonly string $property, string ...$options)
    {
        $this->options              = $options;
    }

    #[\Override]
    public function getAspectName(): string
    {
        return 'Descriptions\\Description';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return $this->options;
    }
}
