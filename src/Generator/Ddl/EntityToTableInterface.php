<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Dsl\Ddl\TableInterface;

interface EntityToTableInterface
{
    public function generate(): TableInterface;
}
