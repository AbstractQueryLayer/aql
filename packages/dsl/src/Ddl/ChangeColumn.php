<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

class ChangeColumn extends DdlStatementAbstract implements ChangeColumnInterface
{
    public function __construct(protected string $oldColumnName, ColumnDefinitionInterface $columnDefinition)
    {
        parent::__construct();

        $this->childNodes[self::DEFINITION] = $columnDefinition;
    }

    #[\Override]
    public function getOldColumnName(): string
    {
        return $this->oldColumnName;
    }

    /**
     * @return ColumnDefinition
     */
    #[\Override]
    public function getColumnDefinition(): ColumnDefinitionInterface
    {
        return $this->childNodes[self::DEFINITION];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }

    #[\Override]
    protected function generateResult(): string
    {
        return $this->escape($this->oldColumnName) . ' ' . $this->getColumnDefinition()->getResult();
    }
}
