<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;

/**
 * FROM_UNIXTIME(unix_timestamp[,format]).
 */
final class FromUnixTime extends FunctionReference
{
    public function __construct(NodeInterface $unixTimestamp, NodeInterface|string|null $format = null)
    {
        if (\is_string($format)) {
            $format                 = new Variable($format);
        }

        if ($format === null) {
            parent::__construct('FROM_UNIXTIME', $unixTimestamp);
        } else {
            parent::__construct('FROM_UNIXTIME', $unixTimestamp, $format);
        }
    }
}
