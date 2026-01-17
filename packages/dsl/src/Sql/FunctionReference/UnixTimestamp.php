<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;

final class UnixTimestamp extends FunctionReference
{
    public function __construct(?NodeInterface $date = null)
    {
        if ($date === null) {
            parent::__construct('UNIX_TIMESTAMP');
        } else {
            parent::__construct('UNIX_TIMESTAMP', $date);
        }
    }
}
