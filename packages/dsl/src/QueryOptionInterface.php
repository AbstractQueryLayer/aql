<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface QueryOptionInterface extends NodeInterface
{
    final public const string OPTION_NAME = 'n';

    final public const string OPTION_VALUE = 'v';

    final public const string OPTION_HIDDEN = 'h';

    public function getOptionName(): string;

    public function getOptionValue(): string|int|float|bool;
}
