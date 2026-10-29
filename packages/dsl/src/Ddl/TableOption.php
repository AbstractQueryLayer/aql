<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Sql\RawSql;

class TableOption extends DdlStatementAbstract implements TableOptionInterface
{
    public function __construct(protected string $option, protected string|array|int|float|RawSql $value)
    {
        parent::__construct();
    }

    #[\Override]
    protected function generateResult(): string
    {
        $value                      = '';

        // A string is a quoted literal of the source, such as a COMMENT; a name such as an ENGINE is a RawSql.
        if (\is_string($this->value)) {
            $value                  = $this->quote($this->value);
        } elseif (\is_scalar($this->value)) {
            $value                  = (string) $this->value;
        } elseif ($this->value instanceof RawSql) {
            $value                  = $this->value->getResult();
        } elseif (\is_array($this->value)) {

            $value                  = [];

            foreach ($this->value as $item) {

                if ($item instanceof RawSql) {
                    $item           = $item->getResult();
                }

                $value[]            = (string) $item;
            }

            $value                  = \implode(' ', $value);
        }

        return $this->option . ' ' . $value;
    }
}
