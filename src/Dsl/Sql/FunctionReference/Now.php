<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

final class Now extends FunctionReference
{
    public function __construct()
    {
        parent::__construct('NOW');
    }
}
