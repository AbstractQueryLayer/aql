<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\Exceptions\BaseException;

class QueryException extends BaseException
{
    public function __construct(array $parameters)
    {
        if (!empty($parameters['query']) && $parameters['query'] instanceof BasicQueryInterface) {
            $parameters['query']    = $parameters['query']->getAql();
        }

        $tags                       = $parameters['tags'] ?? [];

        if (!\is_array($tags)) {
            $tags                   = [];
        }

        $parameters['tags']         = $tags + ['aql', 'query'];

        // Use the first error as parent exception
        $parent                     = null;

        if (!empty($parameters['errors']) && \count($parameters['errors']) === 1) {
            $parent                 = $parameters['errors'][0];
        }

        parent::__construct($parameters, 0, $parent);
    }
}
