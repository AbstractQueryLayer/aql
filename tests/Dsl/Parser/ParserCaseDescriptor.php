<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

class ParserCaseDescriptor
{
    public function __construct(protected string $sql, protected string $aql = '', protected string $name = '') {}

    public function getTestCaseName(): string
    {
        return $this->name;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getAql(): string
    {
        return $this->aql === '' ? $this->sql : $this->aql;
    }
}
