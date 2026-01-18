<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

final class NameDefinition extends DdlStatementAbstract
{
    public function __construct(private readonly string $name)
    {
        parent::__construct();
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->escape($this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return $this->escape($this->name);
    }
}
