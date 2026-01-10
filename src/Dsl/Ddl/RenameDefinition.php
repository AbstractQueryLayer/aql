<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

/**
 * ## RenameDefinition
 * SQL expression
 * old_col_name TO new_col_name.
 *
 */
class RenameDefinition extends DdlStatementAbstract
{
    public function __construct(protected string $oldName, protected string $newName)
    {
        parent::__construct();
    }

    #[\Override]
    protected function generateResult(): string
    {
        return $this->escape($this->oldName) . ' TO ' . $this->escape($this->newName);
    }
}
