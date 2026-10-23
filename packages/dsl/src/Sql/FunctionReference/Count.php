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

    /** RawSql has no AQL form, so the function writes its `*` itself for the parser to read back. */
    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return 'COUNT(*)';
    }
}
