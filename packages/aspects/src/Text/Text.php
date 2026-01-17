<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Text;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class Text implements AspectDescriptorInterface
{
    final public const string WITH_TYPE    = 'withType';

    final public const string WITH_HISTORY = 'withHistory';

    final public const string TYPE_HTML    = 'html';

    final public const string TYPE_TEXT    = 'text';

    final public const string TYPE_MARKDOWN = 'markdown';

    public const TEXT               = 'text';

    /**
     * @var string[]
     */
    protected array $options;

    public function __construct(public readonly string $property = self::TEXT, string ...$options)
    {
        $this->options              = $options;
    }

    #[\Override]
    public function getAspectName(): string
    {
        return 'Text\\Text';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return $this->options;
    }
}
