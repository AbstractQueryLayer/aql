<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

class ConstantNull extends Constant
{
    public function __construct()
    {
        parent::__construct(null, 'null');
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return 'NULL';
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return 'NULL';
    }
}
