<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Sql\RawSql;

final class Count extends FunctionReference
{
    public function __construct()
    {
        parent::__construct('COUNT', new RawSql('*'));
    }
}
