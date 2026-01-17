<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

/**
 * SHOW TABLES [IN database] [LIKE 'pattern'] statement.
 *
 */
class ShowTables extends DdlStatementAbstract
{
    public function __construct(protected string $like = '', protected string $database = '')
    {
        parent::__construct();
    }

    public function getLike(): string
    {
        return $this->like;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }

    #[\Override]
    protected function generateResult(): string
    {
        $result                     = 'SHOW TABLES';

        if ($this->database !== '') {
            $result                 .= ' IN ' . $this->database;
        }

        if ($this->like !== '') {
            $result                 .= ' LIKE ' . $this->quote($this->like);
        }

        return $result;
    }
}
