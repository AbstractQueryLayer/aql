<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Dsl\BasicQueryInterface;

class UnknownQueryAction extends QueryException
{
    protected string $template      = 'Unknown query action {action}. Query: {query}';

    public function __construct(string $action, BasicQueryInterface $query)
    {
        parent::__construct(['action' => $action, 'query' => $query]);
    }
}
