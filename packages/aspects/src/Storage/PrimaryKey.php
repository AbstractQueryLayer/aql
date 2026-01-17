<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use Attribute;
use IfCastle\AOP\AspectDescriptorInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class PrimaryKey implements AspectDescriptorInterface
{
    public const string BIG_INT     = 'big_int';

    public const string INT         = 'int';

    public const string UUID        = 'uuid';

    public const string ULID        = 'ulid';

    public const string DEFAULT     = 'default';

    public function __construct(
        public readonly string $type = '',
        public readonly string $name = 'id',
        public readonly bool $isAutoIncrement = true
    ) {}


    #[\Override]
    public function getAspectName(): string
    {
        return 'Storage\\PrimaryKey';
    }

    #[\Override]
    public function getAspectOptions(): array
    {
        return [
            'type'                  => $this->type,
            'name'                  => $this->name,
            'isAutoIncrement'       => $this->isAutoIncrement,
        ];
    }
}
